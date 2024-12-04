<?php

/**
 * Marc record class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2012-2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.1 GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */

namespace RecordManager\Finna\Record;

use RecordManager\Base\Database\DatabaseInterface as Database;
use RecordManager\Base\Marc\Marc as MarcHandler;
use RecordManager\Base\Record\CreateRecordTrait;
use RecordManager\Base\Record\Marc\FormatCalculator;
use RecordManager\Base\Record\PluginManager as RecordPluginManager;
use RecordManager\Base\Utils\LcCallNumber;
use RecordManager\Base\Utils\Logger;
use RecordManager\Base\Utils\MetadataUtils;

use function boolval;
use function in_array;
use function is_array;
use function strlen;

/**
 * Marc record class
 *
 * This is a class for processing MARC records.
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */
class Marc extends \RecordManager\Base\Record\Marc
{
    use AuthoritySupportTrait;
    use CreateRecordTrait;
    use DateSupportTrait;
    use MediaTypeTrait;

    /**
     * Record plugin manager
     *
     * @var RecordPluginManager
     */
    protected $recordPluginManager;

    /**
     * Strings in field 300 that signify that the work is illustrated.
     *
     * @var array
     */
    protected $illustrationStrings = [
        'ill.', 'illus.', 'kuv.', 'kuvitettu', 'illustrated',
    ];

    /**
     * Extra data to be included in Solr fields e.g. from component parts
     *
     * @var array<string, string|array<int, string>>
     */
    protected $extraFields = [];

    /**
     * Default field for geographic coordinates
     *
     * @var string
     */
    protected $defaultGeoField = 'location_geo';

    /**
     * Default field for geographic center coordinates
     *
     * @var string
     */
    protected $defaultGeoCenterField = 'center_coords';

    /**
     * Default field for geographic center coordinates
     *
     * @var string
     */
    protected $defaultGeoDisplayField = '';

    /**
     * Cache for record format
     *
     * @var mixed
     */
    protected $cachedFormat = null;

    /**
     * Patterns for matching publication date ranges (case-insentive)
     *
     * @var array
     */
    protected $publicationDateRangePatterns = [
        '/between\s+(?<begin>\d{4})\s+and\s+(?<end>\d{4})/',
        '/vuosien\s+(?<begin>\d{4})\s+ja\s+(?<end>\d{4})\s+välillä/',
        '/(?<begin>\d{4})\s+-\s+(?<end>\d{4})/',
    ];

    /**
     * Field specs for ISBN fields
     *
     * 'type' can be 'normal' or 'invalid'; for 'invalid', an invalid value isn't
     * stored in the warnings field.
     *
     * @var array
     */
    protected $isbnFields = [
        [
            'type' => 'normal',
            'selector' => [[MarcHandler::GET_NORMAL, '020', ['a']]],
        ],
        [
            'type' => 'invalid',
            'selector' => [[MarcHandler::GET_NORMAL, '020', ['z']]],
        ],
        [
            'type' => 'combined',
            'selector' => [[MarcHandler::GET_NORMAL, '773', ['z']]],
        ],
        [
            'type' => 'combined',
            'selector' => [[MarcHandler::GET_NORMAL, '776', ['z']]],
        ],
    ];

    /**
     * Field specs for ISSN fields
     *
     * 'type' can be 'normal', 'combined' or 'invalid'; it's not currently used but
     * exists for future needs and compatibility with $isbnFields.
     *
     * @var array
     */
    protected $issnFields = [
        [
            'type' => 'normal',
            'selector' => [[MarcHandler::GET_NORMAL, '022', ['a']]],
        ],
    ];

    /**
     * Constructor
     *
     * @param array               $config              Main configuration
     * @param array               $dataSourceConfig    Data source settings
     * @param Logger              $logger              Logger
     * @param MetadataUtils       $metadataUtils       Metadata utilities
     * @param callable            $recordCallback      MARC record creation callback
     * @param FormatCalculator    $formatCalculator    Record format calculator
     * @param RecordPluginManager $recordPluginManager Record plugin manager
     */
    public function __construct(
        $config,
        $dataSourceConfig,
        Logger $logger,
        MetadataUtils $metadataUtils,
        callable $recordCallback,
        FormatCalculator $formatCalculator,
        RecordPluginManager $recordPluginManager
    ) {
        parent::__construct(
            $config,
            $dataSourceConfig,
            $logger,
            $metadataUtils,
            $recordCallback,
            $formatCalculator
        );

        $this->recordPluginManager = $recordPluginManager;
        $this->initMediaTypeTrait($config);
    }

    /**
     * Set record data
     *
     * @param string $source    Source ID
     * @param string $oaiID     Record ID received from OAI-PMH (or empty string for
     *                          file import)
     * @param string $data      Record metadata
     * @param array  $extraData Extra metadata
     *
     * @return void
     */
    public function setData($source, $oaiID, $data, $extraData)
    {
        $this->extraFields = [];
        $this->cachedFormat = null;
        parent::setData($source, $oaiID, $data, $extraData);
    }

    /**
     * Get the underlying MARC record
     *
     * @return \RecordManager\Base\Marc\Marc
     */
    public function getRecord(): \RecordManager\Base\Marc\Marc
    {
        return $this->record;
    }

    /**
     * Normalize the record (optional)
     *
     * @return void
     */
    public function normalize()
    {
        parent::normalize();

        // Kyyti enumeration from 362 to title
        if ($this->source == 'kyyti' && $this->record->getField('245')) {
            $enum = $this->getFieldSubfields('362', ['a']);
            if ($enum) {
                $this->record->addFieldSubfield('245', 0, 'n', $enum);
            }
        }
    }

    /**
     * Return fields to be indexed in Solr
     *
     * @param Database $db Database connection. Omit to avoid database lookups for
     *                     related records.
     *
     * @return array<string, mixed>
     *
     * @psalm-suppress DuplicateArrayKey
     * @psalm-suppress NoValue
     */
    public function toSolrArray(Database $db = null)
    {
        $data = parent::toSolrArray($db);

        $leader = $this->record->getLeader();
        $field008 = $this->record->getControlField('008');

        if (empty($data['author'])) {
            foreach ($this->record->getFields('110') as $field110) {
                $author = $this->record->getSubfield($field110, 'a');
                if ($author) {
                    $data['author'][] = $author;
                    $role = $this->record->getSubfield($field110, '4');
                    if (!$role) {
                        $role = $this->record->getSubfield($field110, 'e');
                    }
                    $data['author_role'][] = $role
                        ? $this->metadataUtils->normalizeRelator($role) : '';
                }
            }
        }

        $primaryAuthors = $this->getPrimaryAuthors();
        $secondaryAuthors = $this->getSecondaryAuthors();
        $corporateAuthors = $this->getCorporateAuthors();
        $data['author2_id_str_mv'] = [
            ...$this->addNamespaceToAuthorityIds($primaryAuthors['ids'], 'author'),
            ...$this->addNamespaceToAuthorityIds($secondaryAuthors['ids'], 'author'),
            ...$this->addNamespaceToAuthorityIds($corporateAuthors['ids'], 'author'),
        ];
        $data['author2_id_role_str_mv'] = [
            ...$this->addNamespaceToAuthorityIds(
                $primaryAuthors['idRoles'],
                'author'
            ),
            ...$this->addNamespaceToAuthorityIds(
                $secondaryAuthors['idRoles'],
                'author'
            ),
            ...$this->addNamespaceToAuthorityIds(
                $corporateAuthors['idRoles'],
                'author'
            ),
        ];

        if (isset($data['publishDate'])) {
            $data['main_date_str']
                = $this->metadataUtils->extractYear($data['publishDate'][0]);
            $data['main_date']
                = $this->validateDate($data['main_date_str'] . '-01-01T00:00:00Z');
        }
        if ($range = $this->getPublicationDateRange()) {
            $data['search_daterange_mv'][] = $data['publication_daterange']
                = $this->dateRangeToStr($range);
        }
        $data['publication_place_txt_mv'] = $this->metadataUtils->arrayTrim(
            $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '260', ['a']],
                ]
            ),
            ' []'
        );
        if (empty($data['publication_place_txt_mv'])) {
            $fields = $this->record->getFields('264');
            foreach ($fields as $field) {
                if ($this->record->getIndicator($field, 2) == '1') {
                    $data['publication_place_txt_mv'][]
                        = $this->metadataUtils->stripTrailingPunctuation(
                            $this->record->getSubfield($field, 'a')
                        );
                }
            }
        }

        $data['subtitle_lng_str_mv'] = $this->getSubtitleLanguages();
        $data['original_lng_str_mv'] = $this->getOriginalLanguages();

        // 979cd = component part authors
        // 900, 910, 911 = Finnish reference field
        foreach (
            $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_BOTH, '979', ['c']],
                    [MarcHandler::GET_BOTH, '979', ['d']],
                    [MarcHandler::GET_BOTH, '900', ['a']],
                    [MarcHandler::GET_BOTH, '910', ['a', 'b']],
                    [MarcHandler::GET_BOTH, '911', ['a', 'e']],
                ],
                false,
                true,
                true
            ) as $field
        ) {
            $field = trim($field);
            if ($field) {
                $data['author2'][] = $field;
                $data['author2_role'][] = '';
            }
        }
        // 979l = component part author id's
        foreach ($this->record->getFields('979') as $field) {
            $ids = $this->getSubfieldsArray($field, ['l']);
            $data['author2_id_str_mv'] = [
                ...$data['author2_id_str_mv'],
                ...$this->addNamespaceToAuthorityIds($ids, 'author'),
            ];
        }

        // Major genre from 008
        if (
            in_array(substr($leader, 6, 1), ['a', 't'])
            && !in_array(substr($leader, 7, 1), ['b', 'i', 's'])
        ) {
            $genre = substr($field008, 33, 1);
            if ('0' === $genre) {
                $data['major_genre_str_mv'] = 'nonfiction';
            } elseif (in_array($genre, ['1', 'd', 'f', 'h', 'j', 'p'], true)) {
                $data['major_genre_str_mv'] = 'fiction';
            }
        }

        // Classifications
        foreach ($this->record->getFields('080') as $field080) {
            $classification = trim($this->record->getSubfield($field080, 'a'));
            $classification .= trim($this->record->getSubfield($field080, 'b'));
            if ($classification) {
                if ($aux = trim($this->getSubfields($field080, ['x']))) {
                    $classification .= " $aux";
                }
                $vocab = 'udk';
                $version = $this->getSubfields($field080, ['2']);
                if (in_array($version, ['1974/fin/fennica', '1974/fin/finuc-s'])) {
                    $vocab .= 'f';
                } elseif (
                    $version
                    && preg_match('/(\d{4})/', $version, $matches)
                    && (int)$matches[1] >= 2009
                ) {
                    $vocab .= '2';
                } else {
                    $vocab .= 'x';
                }
                $data['classification_txt_mv'][] = "$vocab $classification";

                [$mainClass] = explode('.', $classification, 2);
                $mainClass = ".$mainClass";
                if (
                    is_numeric($mainClass)
                    && (!isset($data['major_genre_str_mv'])
                    || $data['major_genre_str_mv'] == 'nonfiction')
                ) {
                    if (
                        $mainClass >= 0.82
                        && $mainClass < 0.9
                        && in_array($aux, ['-1', '-2', '-3', '-4', '-5', '-6', '-8'])
                    ) {
                        $data['major_genre_str_mv'] = 'fiction';
                    } elseif ($mainClass >= 0.78 && $mainClass < 0.79) {
                        $data['major_genre_str_mv'] = 'music';
                    } else {
                        $data['major_genre_str_mv'] = 'nonfiction';
                    }
                }
            }
        }
        $dlc = $this->getFieldsSubfields(
            [[MarcHandler::GET_NORMAL, '050', ['a', 'b']]]
        );
        foreach ($dlc as $classification) {
            $data['classification_txt_mv'][] = 'dlc '
                . mb_strtolower(str_replace(' ', '', $classification), 'UTF-8');
            $data['classification_txt_mv'][] = "dlc $classification";
        }
        $nlm = $this->getFieldsSubfields(
            [[MarcHandler::GET_NORMAL, '060', ['a', 'b']]]
        );
        foreach ($nlm as $classification) {
            $data['classification_txt_mv'][] = 'nlm '
                . mb_strtolower(str_replace(' ', '', $classification), 'UTF-8');
            $data['classification_txt_mv'][] = "nlm $classification";
        }
        foreach ($this->record->getFields('084') as $field) {
            $source = $this->record->getSubfield($field, '2');
            $classification = $this->getSubfields($field, ['a', 'b']);
            if ($source) {
                $data['classification_txt_mv'][] = "$source "
                    . mb_strtolower(str_replace(' ', '', $classification), 'UTF-8');
                $data['classification_txt_mv'][] = "$source $classification";
            }
            // Major genre
            if (
                $source == 'ykl'
                && (!isset($data['major_genre_str_mv'])
                || $data['major_genre_str_mv'] == 'nonfiction')
            ) {
                switch (substr(ltrim($classification, 'L'), 0, 2)) {
                    case '78':
                        $data['major_genre_str_mv'] = 'music';
                        break;
                    case '80':
                    case '81':
                    case '82':
                    case '83':
                    case '84':
                    case '85':
                        $data['major_genre_str_mv'] = 'fiction';
                        break;
                    default:
                        $data['major_genre_str_mv'] = 'nonfiction';
                        break;
                }
            }
        }
        // Extra classifications
        if ($extraClassifications = $this->getExtraClassifications()) {
            $data['classification_txt_mv'] = [
                ...(array)($data['classification_txt_mv'] ?? []),
                ...$extraClassifications,
            ];
        }

        // Keep classification_str_mv for backward-compatibility for now
        if (isset($data['classification_txt_mv'])) {
            $data['classification_str_mv'] = $data['classification_txt_mv'];
        }

        // Original Study Number
        $data['ctrlnum'] = [
            ...(array)$data['ctrlnum'],
            ...$this->getFieldsSubfields([[MarcHandler::GET_NORMAL, '036', ['a']]]),
        ];

        // Source
        $data['source_str_mv'] = $this->source;
        $data['datasource_str_mv'] = [$this->source];

        // ISSN processing
        foreach ($data['issn'] as &$value) {
            $value = str_replace('-', '', $value);
        }
        unset($value);
        $data['other_issn_isn_mv'] = $data['other_issn_str_mv']
            = $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '022', ['y']],
                    [MarcHandler::GET_NORMAL, '440', ['x']],
                    [MarcHandler::GET_NORMAL, '480', ['x']],
                    [MarcHandler::GET_NORMAL, '490', ['x']],
                    [MarcHandler::GET_NORMAL, '730', ['x']],
                    [MarcHandler::GET_NORMAL, '776', ['x']],
                    [MarcHandler::GET_NORMAL, '830', ['x']],
                ]
            );
        foreach ($data['other_issn_str_mv'] as &$value) {
            $value = str_replace('-', '', $value);
        }
        unset($value);
        $data['linking_issn_str_mv'] = $this->getFieldsSubfields(
            [[MarcHandler::GET_NORMAL, '022', ['l']]]
        );
        foreach ($data['linking_issn_str_mv'] as &$value) {
            $value = str_replace('-', '', $value);
        }
        unset($value);

        // URLs
        $onlineUrls = $this->getLinkData();
        foreach ($onlineUrls as $link) {
            $link['source'] = $this->source;
            $data['online_urls_str_mv'][] = json_encode($link);
        }
        $data['media_type_str_mv'] = array_values(
            array_unique(
                array_column($onlineUrls, 'mediaType')
            )
        );

        if ($this->isOnline()) {
            $data['online_boolean'] = '1';
            $data['online_str_mv'] = $this->source;
            if ($this->isFreeOnline()) {
                $data['free_online_boolean'] = '1';
                $data['free_online_str_mv'] = $this->source;
            }
        }

        // Holdings
        $data['holdings_txtP_mv'] = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '852', ['a', 'b', 'h', 'z']],
                [MarcHandler::GET_NORMAL, '952', ['b', 'c', 'o', 'h']],
            ]
        );
        if (!empty($data['holdings_txtP_mv'])) {
            $updateFunc = function (&$val, $k, $source) {
                $val .= " $source";
            };
            array_walk($data['holdings_txtP_mv'], $updateFunc, $this->source);
        }

        // Shelving location in building_sub_str_mv
        $subBuilding = $this->getDriverParam('subBuilding', '');
        if ('1' === $subBuilding) { // true
            $subBuilding = 'c';
        }
        if ($subBuilding) {
            foreach ($this->record->getFieldsSubfields('852', str_split($subBuilding)) as $location) {
                if ('' !== $location) {
                    $data['building_sub_str_mv'][] = $location;
                }
            }
        }
        $itemSubBuilding = $this->getDriverParam('itemSubBuilding', $subBuilding);
        if ($itemSubBuilding) {
            foreach ($this->record->getFieldsSubfields('952', str_split($itemSubBuilding)) as $location) {
                if ('' !== $location) {
                    $data['building_sub_str_mv'][] = $location;
                }
            }
        }

        // Collection code from MARC fields
        $collectionFields = $this->getDriverParam('collectionFields', '');
        if ($collectionFields) {
            foreach (explode(':', $collectionFields) as $fieldSpec) {
                $fieldTag = substr($fieldSpec, 0, 3);
                $subfields = str_split(substr($fieldSpec, 3));
                foreach ($this->record->getFields($fieldTag) as $field) {
                    $subfieldArray
                        = $this->getSubfieldsArray($field, $subfields);
                    foreach ($subfieldArray as $subfield) {
                        $data['collection'] = $subfield;
                    }
                }
            }
        }

        // Access restrictions
        if ($restrictions = $this->getAccessRestrictions()) {
            $data['restricted_str'] = $restrictions;
        }

        // NBN
        foreach ($this->record->getFields('015') as $field015) {
            $nbn = $this->record->getSubfield($field015, 'a');
            $data['nbn_isn_mv'] = $nbn;
        }

        // ISMN, ISRC, UPC, EAN
        foreach ($this->record->getFields('024') as $field024) {
            $ind1 = $this->record->getIndicator($field024, 1);
            switch ($ind1) {
                case '0':
                    $isrc = $this->record->getSubfield($field024, 'a');
                    $data['isrc_isn_mv'][] = $isrc;
                    break;
                case '1':
                    $upc = $this->record->getSubfield($field024, 'a');
                    $data['upc_isn_mv'][] = $upc;
                    break;
                case '2':
                    $ismn = $this->record->getSubfield($field024, 'a');
                    $ismn = str_replace('-', '', $ismn);
                    if (!preg_match('{([0-9]{13})}', $ismn, $matches)) {
                        continue 2; // foreach
                    }
                    $data['ismn_isn_mv'][] = $matches[1];
                    break;
                case '3':
                    $ean = $this->record->getSubfield($field024, 'a');
                    $ean = str_replace('-', '', $ean);
                    if (!preg_match('{([0-9]{13})}', $ean, $matches)) {
                        continue 2; // foreach
                    }
                    $data['ean_isn_mv'][] = $matches[1];
                    break;
            }
        }

        // Publisher or distributor number
        foreach ($this->getPublisherNumbers() as $current) {
            $number = $current['id'];
            if ('' !== $current['source']) {
                $number = '(' . $current['source'] . ')' . $number;
            }
            $data['pdn_str_mv'][] = $number;
        }

        // Identifiers from component parts (type as a leading string)
        foreach (
            $this->getFieldsSubfields(
                [[MarcHandler::GET_NORMAL, '979', ['k']]],
                false,
                true,
                true
            ) as $identifier
        ) {
            $parts = explode(' ', $identifier, 2);
            if (!isset($parts[1])) {
                continue;
            }
            switch ($parts[0]) {
                case 'ISBN':
                    $data['isbn'][] = $parts[1];
                    break;
                case 'ISSN':
                    $data['issn'][] = $parts[1];
                    break;
                case 'ISRC':
                    $data['isrc_isn_mv'][] = $parts[1];
                    break;
                case 'UPC':
                    $data['upc_isn_mv'][] = $parts[1];
                    break;
                case 'ISMN':
                    $data['ismn_isn_mv'][] = $parts[1];
                    break;
                case 'EAN':
                    $data['ean_isn_mv'][] = $parts[1];
                    break;
            }
        }

        // Project ID in 960 (Fennica)
        if ($this->getDriverParam('projectIdIn960', false)) {
            $data['project_id_str_mv'] = $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '960', ['a']],
                ]
            );
        }

        // Hierarchical Categories (database records in Voyager)
        foreach ($this->record->getFields('886') as $field886) {
            if (
                $this->record->getIndicator($field886, 1) != '2'
                || $this->record->getSubfield($field886, '2') != 'local'
            ) {
                continue;
            }
            $type = $this->record->getSubfield($field886, 'a');
            if (in_array($type, ['aineistotyyppi', 'resurstyp'])) {
                $resourceType = $this->record->getSubfield($field886, 'c');
                if (in_array($resourceType, ['tietokanta', 'databas'])) {
                    $data['format'] = 'Database';
                    foreach ($this->record->getFields('035') as $f035) {
                        if ($originalId = $this->record->getSubfield($f035, 'a')) {
                            $originalId
                                = preg_replace('/^\(.*?\)/', '', $originalId);
                            $data['original_id_str_mv'][] = $originalId;
                        }
                    }
                }
                $access = $this->metadataUtils->normalizeKey(
                    $this->getFieldSubfields('506', ['f']),
                    'NFKC'
                );
                switch ($access) {
                    case 'unrestricted':
                    case 'unrestrictedonlineaccess':
                        // no restrictions
                        break;
                    default:
                        $data['restricted_str'] = 'restricted';
                        break;
                }
            }
            if (in_array($type, ['kategoria', 'kategori'])) {
                $category = $this->metadataUtils->stripTrailingPunctuation(
                    $this->record->getSubfield($field886, 'c')
                );
                $sub = $this->metadataUtils->stripTrailingPunctuation(
                    $this->record->getSubfield($field886, 'd')
                );
                if ($sub) {
                    $category .= "/$sub";
                }
                $data['category_str_mv'][] = $category;
            }
        }

        // Hierarchical categories (e.g. SFX)
        if ($this->getDriverParam('categoriesIn650', false)) {
            foreach ($this->record->getFields('650') as $field650) {
                if ($this->record->getSubfield($field650, '0')) {
                    // Source specified -- assume not a category
                    continue;
                }
                $category = $this->record->getSubfield($field650, 'a');
                $category = trim(str_replace(['/', '\\'], '', $category));
                if (!$category) {
                    continue;
                }
                $category
                    = $this->metadataUtils->stripTrailingPunctuation($category);
                $sub = $this->record->getSubfield($field650, 'x');
                $sub = trim(str_replace(['/', '\\'], '', $sub));
                if ($sub) {
                    $sub = $this->metadataUtils->stripTrailingPunctuation($sub);
                    $category .= "/$sub";
                }
                $data['category_str_mv'][] = $category;
            }
        }

        // Call numbers
        $data['callnumber-first'] = strtoupper(
            str_replace(
                ' ',
                '',
                $this->getFirstFieldSubfields(
                    [
                        [MarcHandler::GET_NORMAL, '080', ['a', 'b']],
                        [MarcHandler::GET_NORMAL, '084', ['a', 'b']],
                        [MarcHandler::GET_NORMAL, '050', ['a', 'b']],
                    ]
                )
            )
        );
        $data['callnumber-raw'] = array_map(
            'strtoupper',
            $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '080', ['a', 'b']],
                    [MarcHandler::GET_NORMAL, '084', ['a', 'b']],
                ]
            )
        );
        $data['callnumber-sort'] = '';
        if (!empty($data['callnumber-raw'])) {
            $data['callnumber-sort'] = $data['callnumber-raw'][0];
        }
        $lccn = array_map(
            'strtoupper',
            $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '050', ['a', 'b']],
                ]
            )
        );
        if ($lccn) {
            $data['callnumber-raw'] = [
                ...$data['callnumber-raw'],
                ...$lccn,
            ];
            if (empty($data['callnumber-sort'])) {
                // Try to find a valid call number
                $firstCn = null;
                foreach ($lccn as $callnumber) {
                    $cn = new LcCallNumber($callnumber);
                    if (null === $firstCn) {
                        $firstCn = $cn;
                    }
                    if ($cn->isValid()) {
                        $data['callnumber-sort'] = $cn->getSortKey();
                        break;
                    }
                }
                if (empty($data['callnumber-sort'])) {
                    // No valid call number, take first
                    $data['callnumber-sort'] = $cn->getSortKey();
                }
            }
        }

        if ($rights = $this->getUsageRights()) {
            $data['usage_rights_str_mv'] = $rights;
            $data['usage_rights_ext_str_mv'] = $rights;
        }

        // Author facet
        $primaryAuthors = $this->getPrimaryAuthorsFacet();
        $secondaryAuthors = $this->getSecondaryAuthorsFacet();
        $corporateAuthors = $this->getCorporateAuthorsFacet();
        $data['author_facet'] = array_map(
            function ($s) {
                return preg_replace('/\s+/', ' ', $s);
            },
            [
                ...(array)$primaryAuthors['names'],
                ...(array)$secondaryAuthors['names'],
                ...(array)$corporateAuthors['names'],
            ]
        );

        if ('VideoGame' === $data['format']) {
            if ($platforms = $this->getGamePlatformIds()) {
                $data['format'] = [['VideoGame', reset($platforms)]];
                $data['format_ext_str_mv'] = [];
                foreach ($platforms as $platform) {
                    $data['format_ext_str_mv'] = [['VideoGame', $platform]];
                }
            }
        } elseif ('Dissertation' === $data['format']) {
            if ('m' === substr($leader, 7, 1)) {
                $data['format_ext_str_mv'] = (array)$data['format'];
                if (
                    'o' === substr($field008, 23, 1)
                    || 'cr' === substr($this->record->getControlField('007'), 0, 2)
                ) {
                    $data['format_ext_str_mv'][] = 'eBook';
                } else {
                    $data['format_ext_str_mv'][] = 'Book';
                }
            }
        } else {
            $data['format_ext_str_mv'] = $data['format'];
        }

        $availableBuildings = $this->getAvailableItemsBuildings();
        if ($availableBuildings) {
            $data['building_available_str_mv'] = $availableBuildings;
            $data['source_available_str_mv'] = $this->source;
        }

        // Additional authority ids
        $data['topic_id_str_mv'] = $this->getTopicIDs();
        $data['geographic_id_str_mv'] = $this->getGeographicTopicIDs();

        // Make sure center_coords is single-valued
        if (!empty($data['center_coords']) && is_array($data['center_coords'])) {
            $data['center_coords'] = $data['center_coords'][0];
        }

        $data['description'] = implode(
            ' ',
            $this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '520', ['a']],
                ]
            )
        );

        // Additional IDs from repeated 001 (Sierra):
        $ids = $this->record->getFields('001');
        array_shift($ids);
        if ($ids) {
            $data['ctrlnum'] = [
                ...($data['ctrlnum'] ?? []),
                ...$ids,
            ];
        }

        // Order and item count summary:
        foreach ($this->record->getFields('852') as $field) {
            $type = $this->record->getSubfield($field, '9');
            if (!$type) {
                continue;
            }
            $count = (int)$this->record->getSubfield($field, 't');
            if ('orders' === $type) {
                $data['orders_int'] = $count;
            } elseif ('items' === $type) {
                $data['items_int'] = $count;
            }
        }
        foreach ($this->record->getFields('952') as $field) {
            $status = (int)$this->record->getSubfield($field, '7');
            if (-1 === $status) {
                $data['orders_int'] = ($data['orders_int'] ?? 0) + 1;
            } else {
                $data['items_int'] = ($data['items_int'] ?? 0) + 1;
            }
        }

        // Performers
        foreach ($this->record->getFields('382') as $performer) {
            $performerData = [];
            $lastIndex = null;
            foreach ($performer['subfields'] ?? [] as $subfield) {
                if (in_array($subfield['code'], ['a', 'b', 'd', 'p'])) {
                    $performerData[] = $subfield['data'] . '/' . $subfield['data'];
                    $lastIndex = array_key_last($performerData);
                }
                if (in_array($subfield['code'], ['n', 'e']) && $lastIndex !== null) {
                    $performerData[$lastIndex] .= ' (' . $subfield['data'] . ')';
                }
                if ('s' === $subfield['code']) {
                    $data['performer_total_int_mv'][] = $subfield['data'];
                }
            }
            if (!empty($performerData)) {
                $data['performer_str_mv'] = array_merge($data['performer_str_mv'] ?? [], $performerData);
            }
        }

        // Merge any extra fields from e.g. merged component parts (also converts any
        // single-value field to an array):
        foreach ($this->extraFields as $field => $fieldData) {
            $data[$field] = array_merge(
                (array)($data[$field] ?? []),
                (array)$fieldData
            );
        }

        // Process any extra data
        if ($date = $this->metadataUtils->validateDate($this->extraData['catalogDate'] ?? null)) {
            $data['catalog_date'] = date('Y-m-d', $date) . 'T00:00:00Z';
        }

        return $data;
    }

    /**
     * Get ids for described authors.
     *
     * @return array
     */
    public function getAuthorTopicIDs(): array
    {
        $fieldTags = ['600', '610', '611'];
        $result = [];
        foreach ($fieldTags as $tag) {
            foreach ($this->record->getFields($tag) as $field) {
                if ($id = $this->getIDFromField($field)) {
                    $result[] = $id;
                }
            }
        }
        return $this->addNamespaceToAuthorityIds($result, 'topic');
    }

    /**
     * Get component part metadata for embedding to host record
     *
     * @return array
     */
    public function getComponentPartMetadata(): array
    {
        $title = $this->getFieldSubfields('245', ['a', 'b', 'n', 'p']);
        $uniformTitle = $this->getFieldSubfields('240', ['a', 'n', 'p']);
        if (!$uniformTitle) {
            $uniformTitle = $this->getFieldSubfields('130', ['a', 'n', 'p']);
        }
        $additionalTitles = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '740', ['a']],
            ]
        );
        $varyingTitles = $this->getFieldsSubfields(
            [[MarcHandler::GET_NORMAL, '246', ['a', 'b', 'n', 'p']]]
        );
        $authors = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '100', ['a', 'e']],
                [MarcHandler::GET_NORMAL, '110', ['a', 'e']],
            ]
        );
        $additionalAuthors = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '700', ['a', 'e']],
                [MarcHandler::GET_NORMAL, '710', ['a', 'e']],
            ]
        );
        $authorIds = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '100', ['0']],
                [MarcHandler::GET_NORMAL, '110', ['0']],
                [MarcHandler::GET_NORMAL, '700', ['0']],
                [MarcHandler::GET_NORMAL, '710', ['0']],
            ]
        );
        $durations = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '306', ['a']],
            ]
        );
        $languages = [substr($this->record->getControlField('008'), 35, 3)];
        $languages = array_unique(
            [
                ...$languages,
                ...$this->getFieldsSubfields(
                    [
                        [MarcHandler::GET_NORMAL, '041', ['a']],
                        [MarcHandler::GET_NORMAL, '041', ['d']],
                    ],
                    false,
                    true,
                    true
                ),
            ]
        );
        $languages = $this->metadataUtils->normalizeLanguageStrings($languages);
        $originalLanguages = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '041', ['h']],
            ],
            false,
            true,
            true
        );
        $originalLanguages
            = $this->metadataUtils->normalizeLanguageStrings($originalLanguages);
        $subtitleLanguages = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '041', ['j']],
            ],
            false,
            true,
            true
        );
        $subtitleLanguages
            = $this->metadataUtils->normalizeLanguageStrings($subtitleLanguages);

        $identifierFields = [
            'ISBN' => $this->isbnFields,
            'ISSN' => [
                [
                    'type' => 'normal',
                    'selector' => [[MarcHandler::GET_NORMAL, '022', ['a']]],
                ],
            ],
            'OAN' => [
                [
                    'type' => 'normal',
                    'selector' => [[MarcHandler::GET_NORMAL, '025', ['a']]],
                ],
            ],
            'FI' => [
                [
                    'type' => 'normal',
                    'selector' => [[MarcHandler::GET_NORMAL, '026', ['a', 'b']]],
                ],
            ],
            'STRN' => [
                [
                    'type' => 'normal',
                    'selector' => [[MarcHandler::GET_NORMAL, '027', ['a']]],
                ],
            ],
            'PDN' => [
                [
                    'type' => 'normal',
                    'selector' => [[MarcHandler::GET_NORMAL, '028', ['a', 'b']]],
                ],
            ],
        ];

        $identifiers = [];
        foreach ($identifierFields as $idKey => $identifierField) {
            foreach ($identifierField as $settings) {
                $ids = $this->getFieldsSubfields($settings['selector'], false, true, true);
                $ids = array_map(
                    function ($s) use ($idKey) {
                        return "$idKey $s";
                    },
                    $ids
                );
                $identifiers = [...$identifiers, ...$ids];
            }
        }

        foreach ($this->record->getFields('024') as $field024) {
            $ind1 = $this->record->getIndicator($field024, 1);
            switch ($ind1) {
                case '0':
                    $isrc = $this->record->getSubfield($field024, 'a');
                    $identifiers[] = "ISRC $isrc";
                    break;
                case '1':
                    $upc = $this->record->getSubfield($field024, 'a');
                    $identifiers[] = "UPC $upc";
                    break;
                case '2':
                    $ismn = $this->record->getSubfield($field024, 'a');
                    $ismn = str_replace('-', '', $ismn);
                    if (!preg_match('{([0-9]{13})}', $ismn, $matches)) {
                        continue 2; // foreach
                    }
                    $identifiers[] = 'ISMN ' . $matches[1];
                    break;
                case '3':
                    $ean = $this->record->getSubfield($field024, 'a');
                    $ean = str_replace('-', '', $ean);
                    if (!preg_match('{([0-9]{13})}', $ean, $matches)) {
                        continue 2; // foreach
                    }
                    $identifiers[] = 'EAN ' . $matches[1];
                    break;
            }
        }

        $textIncipits = [];
        foreach ($this->record->getFields('031') as $field031) {
            foreach ($this->getSubfieldsArray($field031, ['t']) as $textIncipit) {
                $textIncipits[] = $textIncipit;
            }
        }

        return compact(
            'title',
            'uniformTitle',
            'additionalTitles',
            'varyingTitles',
            'authors',
            'additionalAuthors',
            'authorIds',
            'durations',
            'languages',
            'originalLanguages',
            'subtitleLanguages',
            'identifiers',
            'textIncipits'
        );
    }

    /**
     * Merge component parts to this record
     *
     * @param \Traversable $componentParts Component parts to be merged
     * @param mixed        $changeDate     Latest database timestamp for the
     *                                     component part set
     *
     * @return int Count of records merged
     *
     * @psalm-suppress DuplicateArrayKey
     */
    public function mergeComponentParts($componentParts, &$changeDate)
    {
        $count = 0;
        $parts = [];
        foreach ($componentParts as $componentPart) {
            if (null === $changeDate || $changeDate < $componentPart['date']) {
                $changeDate = $componentPart['date'];
            }

            $componentRecord = $this->createRecord(
                $componentPart['format'],
                $this->metadataUtils->getRecordData($componentPart, true),
                '',
                $this->source
            );

            $data = $componentRecord->getComponentPartMetadata();
            if ($data['textIncipits']) {
                $this->extraFields['allfields'] = [
                    ...(array)($this->extraFields['allfields'] ?? []),
                    ...(array)$data['textIncipits'],
                ];
                // Text incipit is treated as an alternative title
                $this->extraFields['title_alt'] = [
                    ...(array)($this->extraFields['title_alt'] ?? []),
                    ...(array)$data['textIncipits'],
                ];
            }
            if ($data['varyingTitles']) {
                $this->extraFields['allfields'] = [
                    ...(array)($this->extraFields['allfields'] ?? []),
                    ...(array)$data['varyingTitles'],
                ];
                $this->extraFields['title_alt'] = [
                    ...(array)($this->extraFields['title_alt'] ?? []),
                    ...(array)$data['varyingTitles'],
                ];
            }

            $id = $componentPart['_id'];
            $newField = [
                'subfields' => [
                    ['a' => $id],
                ],
            ];

            if ($data['title']) {
                $newField['subfields'][] = ['b' => $data['title']];
            }
            if ($data['authors']) {
                $newField['subfields'][] = ['c' => array_shift($data['authors'])];
                foreach ($data['authors'] as $author) {
                    $newField['subfields'][] = ['d' => $author];
                }
            }
            foreach ($data['additionalAuthors'] as $addAuthor) {
                $newField['subfields'][] = ['d' => $addAuthor];
            }
            if ($data['uniformTitle']) {
                $newField['subfields'][] = ['e' => $data['uniformTitle']];
            }
            if ($data['durations']) {
                $newField['subfields'][] = ['f' => reset($data['durations'])];
            }
            foreach ($data['additionalTitles'] as $addTitle) {
                $newField['subfields'][] = ['g' => $addTitle];
            }
            foreach ($data['languages'] as $language) {
                if ('|||' !== $language) {
                    $newField['subfields'][] = ['h' => $language];
                }
            }
            foreach ($data['originalLanguages'] as $language) {
                if ('|||' !== $language) {
                    $newField['subfields'][] = ['i' => $language];
                }
            }
            foreach ($data['subtitleLanguages'] as $language) {
                if ('|||' !== $language) {
                    $newField['subfields'][] = ['j' => $language];
                }
            }
            foreach ($data['identifiers'] as $identifier) {
                $newField['subfields'][] = ['k' => $identifier];
            }
            foreach ($data['authorIds'] as $identifier) {
                $newField['subfields'][] = ['l' => $identifier];
            }

            $key = $this->metadataUtils->createIdSortKey($id);
            $parts["$key $count"] = $newField;
            ++$count;
        }
        ksort($parts);
        foreach ($parts as $part) {
            $this->record->addField('979', ' ', ' ', $part['subfields']);
        }
        return $count;
    }

    /**
     * Dedup: Return format from predefined values
     *
     * @return string
     */
    public function getFormat()
    {
        if (null === $this->cachedFormat) {
            $this->cachedFormat = $this->getFormatFunc();
        }
        return $this->cachedFormat;
    }

    /**
     * Check if record has access restrictions.
     *
     * @return string 'restricted' or more specific licence id if restricted,
     * empty string otherwise
     */
    public function getAccessRestrictions()
    {
        if ($result = parent::getAccessRestrictions()) {
            return $result;
        }
        // Access restrictions based on location
        $restricted = $this->getDriverParam('restrictedLocations', '');
        if ($restricted) {
            $restricted = array_flip(
                array_map(
                    'trim',
                    explode(',', $restricted)
                )
            );
        }
        if ($restricted) {
            foreach ($this->record->getFields('852') as $field852) {
                $locationCode = trim($this->record->getSubfield($field852, 'b'));
                if (isset($restricted[$locationCode])) {
                    return 'restricted';
                }
            }
            foreach ($this->record->getFields('952') as $field952) {
                $locationCode = trim($this->record->getSubfield($field952, 'b'));
                if (isset($restricted[$locationCode])) {
                    return 'restricted';
                }
            }
        }
        foreach ($this->record->getFields('540') as $field) {
            $sub3 = $this->metadataUtils->stripTrailingPunctuation(
                $this->record->getSubfield($field, '3')
            );
            if ($sub3 == 'Metadata' || strncasecmp($sub3, 'metadata', 8) == 0) {
                $subA = $this->metadataUtils->stripTrailingPunctuation(
                    $this->record->getSubfield($field, 'a')
                );
                if (strncasecmp($subA, 'ei poimintaa', 12) == 0) {
                    return 'restricted';
                }
            }
        }
        return '';
    }

    /**
     * Check if the record is suppressed.
     *
     * @return bool
     */
    public function getSuppressed()
    {
        if (parent::getSuppressed()) {
            return true;
        }
        if ($this->getDriverParam('kohaNormalization', false)) {
            foreach ($this->record->getFields('942') as $field942) {
                $suppressed = $this->record->getSubfield($field942, 'n');
                return (bool)$suppressed;
            }
        }
        return false;
    }

    /**
     * Dedup: Return unique IDs (control numbers)
     *
     * @return array
     */
    public function getUniqueIDs()
    {
        if (isset($this->resultCache[__METHOD__])) {
            return $this->resultCache[__METHOD__];
        }
        $result = parent::getUniqueIDs();
        // Melinda ID
        foreach ($this->record->getFields('035') as $field) {
            $id = $this->record->getSubfield($field, 'a');
            if (str_starts_with($id, 'FCC')) {
                $idNumber = substr($id, 3);
                if (ctype_digit($idNumber)) {
                    $result[] = "(FI-MELINDA)$idNumber";
                    break;
                }
            }
        }
        $this->resultCache[__METHOD__] = $result;
        return $result;
    }

    /**
     * Get all non-specific topics
     *
     * @return array
     */
    protected function getTopicIDs(): array
    {
        $fieldTags = ['567', '600', '610', '611', '630', '650', '690'];
        $result = [];
        foreach ($fieldTags as $tag) {
            foreach ($this->record->getFields($tag) as $field) {
                if ($id = $this->getIDFromField($field, 'topic')) {
                    $result[] = $id;
                }
            }
        }
        return $this->addNamespaceToAuthorityIds($result, 'topic');
    }

    /**
     * Get identifier from subfield 0. Prefix with source if necessary.
     *
     * @param array  $field MARC field
     * @param string $type  Type of the id
     *
     * @return string
     */
    protected function getIdFromField(array $field, string $type = ''): string
    {
        if ($id = $this->record->getSubfield($field, '0')) {
            if (
                !preg_match('/^https?:/', $id)
            ) {
                // Do not prepend source in front of known authority ids (i.e. already prefixed)
                $regex = $this->getAuthorityIdRegex($type);
                if ($regex && preg_match($regex, $id)) {
                    return $id;
                }
                if ($srcId = $this->getThesaurusId($field)) {
                    return "($srcId)$id";
                }
            }
        }
        return $id;
    }

    /**
     * Get thesaurus ID from second indicator or subfield 2
     *
     * @param array $field MARC field
     *
     * @return string
     */
    protected function getThesaurusId(array $field): string
    {
        $map = [
            't0' => 'LCSH',
            't1' => 'LCCSH',
            't2' => 'MSH',
            't3' => 'NAL',
            't5' => 'CanSH',
            't6' => 'RVM',
        ];
        $ind2 = $this->record->getIndicator($field, 2);
        if ($src = ($map["t$ind2"] ?? '')) {
            return $src;
        }
        if ('7' === $ind2) {
            return $this->record->getSubfield($field, '2');
        }
        return '';
    }

    /**
     * Return format from predefined values
     *
     * @return string
     */
    protected function getFormatFunc()
    {
        // Get 008
        $field008 = $this->record->getControlField('008');

        // Daisy audio books (intentionally before 977 since it's less granular)
        if (substr($field008, 22, 1) === 'f') {
            foreach ($this->record->getFieldsSubfields('347', ['b'], null) as $sub) {
                if (mb_strtolower($sub, 'UTF-8') === 'daisy') {
                    return 'AudioBookDaisy';
                }
            }
        }
        $daisyRules = [
            ['020', 'q', 'daisy'],
            ['028', 'b', 'celia'],
            ['245', 'b', 'daisy-äänikirja'],
            ['300', 'a', 'daisy'],
        ];
        foreach ($daisyRules as [$field, $subfield, $search]) {
            foreach ($this->record->getFieldsSubfields($field, [$subfield], null) as $sub) {
                if (mb_stristr($sub, $search, false, 'UTF-8') !== false) {
                    return 'AudioBookDaisy';
                }
            }
        }

        // OverDrive audio books (intentionally before 977 since it's less granular)
        foreach ($this->record->getFieldsSubfields('380', ['a'], null) as $sub) {
            if (mb_strtolower($sub, 'UTF-8') === 'eaudiobook') {
                return 'AudioBookOverDrive';
            }
        }

        // Custom predefined type in 977a
        $field977a = $this->getFieldSubfields('977', ['a']);
        if ($field977a) {
            if (in_array($field977a, ['3', 't'])) {
                // The format will be mapped to Book/AudioBook/... but keep
                // NonMusical... here for consistency with other types below e.g.
                // for deduplication:
                $possibleFormat = '3' === $field977a
                    ? 'NonmusicalCD' : 'NonmusicalRecording';
                // Helmet audio books
                foreach ($this->record->getFields('336') as $f336) {
                    foreach ($this->record->getSubfields($f336, 'a') as $subA) {
                        if (in_array(mb_strtolower($subA, 'UTF-8'), ['puhe', 'tal'])) {
                            return $possibleFormat;
                        }
                    }
                    foreach ($this->record->getSubfields($f336, 'b') as $subB) {
                        if (mb_strtolower($subB, 'UTF-8') === 'spw') {
                            return $possibleFormat;
                        }
                    }
                }
                $f655a = array_map(
                    function ($s) {
                        return mb_strtolower($s, 'UTF-8');
                    },
                    $this->record->getFieldsSubfields('655', ['a'], null)
                );
                if (array_intersect($f655a, ['äänikirjat', 'ljudböcker'])) {
                    return $possibleFormat;
                }
            }
            return $field977a;
        }

        // Dissertations and Thesis
        if ($this->record->getField('502')) {
            return 'Dissertation';
        }
        $dissTypes = $this->record->getFieldsSubfields('509', ['a']);
        if (!$dissTypes) {
            $dissTypes = $this->record->getFieldsSubfields('920', ['a']);
        }
        if ($dissTypes) {
            foreach ($dissTypes as $dissType) {
                $dissType = mb_strtolower(
                    $this->metadataUtils->normalizeUnicode(
                        $this->metadataUtils->stripTrailingPunctuation($dissType),
                        'NFKC'
                    ),
                    'UTF-8'
                );
                switch ($dissType) {
                    case 'kandidaatintutkielma':
                    case 'kandidaatintyö':
                    case 'kandidatarbete':
                        return 'BachelorsThesis';
                    case 'pro gradu -tutkielma':
                    case 'pro gradu -työ':
                    case 'pro gradu':
                        return 'ProGradu';
                    case 'laudaturtyö':
                    case 'laudaturavh':
                        return 'LaudaturThesis';
                    case 'lisensiaatintyö':
                    case 'lic.avh':
                    case 'licentiatavhandling':
                        return 'LicentiateThesis';
                    case 'diplomityö':
                    case 'diplomarbete':
                        return 'MastersThesis';
                    case 'erikoistyö':
                    case 'vicenot.ex':
                        return 'Thesis';
                    case 'lopputyö':
                    case 'rättsnot.ex':
                        return 'Thesis';
                    case 'amk-opinnäytetyö':
                    case 'yh-examensarbete':
                        return 'BachelorsThesisPolytechnic';
                    case 'ylempi amk-opinnäytetyö':
                    case 'högre yh-examensarbete':
                        return 'MastersThesisPolytechnic';
                }
            }
            return 'Thesis';
        }

        // Get the type of record from leader position 6
        $leader = $this->record->getLeader();
        $typeOfRecord = substr($leader, 6, 1);

        // Get the bibliographic level from leader position 7
        $bibliographicLevel = substr($leader, 7, 1);

        // Board games and video games
        $termsIn655 = null;
        $termIn655 = function (string $term) use (&$termsIn655) {
            if (null === $termsIn655) {
                $termsIn655 = $this->getFieldsSubfields(
                    [[MarcHandler::GET_NORMAL, '655', ['a']]]
                );
                $termsIn655 = array_map(
                    function ($s) {
                        return mb_strtolower($s, 'UTF-8');
                    },
                    $termsIn655
                );
            }
            return in_array($term, $termsIn655);
        };
        if ('r' === $typeOfRecord) {
            $visualType = substr($field008, 33, 1);
            if ('g' === $visualType || $termIn655('lautapelit')) {
                return 'BoardGame';
            }
        } elseif ('m' === $typeOfRecord) {
            $electronicType = substr($field008, 26, 1);
            if ('g' === $electronicType || $termIn655('videopelit')) {
                return 'VideoGame';
            }
        }

        // check the 007 - this is a repeating field
        $fields = $this->record->getControlFields('007');
        $online = false;
        foreach ($fields as $contents) {
            $formatCode = strtoupper(substr($contents, 0, 1));
            $formatCode2 = strtoupper(substr($contents, 1, 1));
            switch ($formatCode) {
                case 'A':
                    switch ($formatCode2) {
                        case 'D':
                            return 'Atlas';
                        default:
                            return 'Map';
                    }
                    // @phpstan-ignore-next-line
                    break;
                case 'C':
                    switch ($formatCode2) {
                        case 'A':
                            return 'TapeCartridge';
                        case 'B':
                            return 'ChipCartridge';
                        case 'C':
                            return 'DiscCartridge';
                        case 'F':
                            return 'TapeCassette';
                        case 'H':
                            return 'TapeReel';
                        case 'J':
                            return 'FloppyDisk';
                        case 'M':
                        case 'O':
                            return 'CDROM';
                        case 'R':
                            // Do not return - this will cause anything with an
                            // 856 field to be labeled as "Electronic"
                            $online = true;
                            break;
                        default:
                            return 'Electronic';
                    }
                    break;
                case 'D':
                    return 'Globe';
                case 'F':
                    return 'Braille';
                case 'G':
                    switch ($formatCode2) {
                        case 'C':
                        case 'D':
                            return 'Filmstrip';
                        case 'T':
                            return 'Transparency';
                        default:
                            return 'Slide';
                    }
                    // @phpstan-ignore-next-line
                    break;
                case 'H':
                    return 'Microfilm';
                case 'K':
                    switch ($formatCode2) {
                        case 'C':
                            return 'Collage';
                        case 'D':
                            return 'Drawing';
                        case 'E':
                            return 'Painting';
                        case 'F':
                            return 'Print';
                        case 'G':
                            return 'Photonegative';
                        case 'J':
                            return 'Print';
                        case 'L':
                            return 'TechnicalDrawing';
                        case 'O':
                            return 'FlashCard';
                        case 'N':
                            return 'Chart';
                        default:
                            return 'Photo';
                    }
                    // @phpstan-ignore-next-line
                    break;
                case 'M':
                    switch ($formatCode2) {
                        case 'F':
                            return 'VideoCassette';
                        case 'R':
                            return 'Filmstrip';
                        default:
                            return 'MotionPicture';
                    }
                    // @phpstan-ignore-next-line
                    break;
                case 'O':
                    return 'Kit';
                case 'Q':
                    return 'MusicalScore';
                case 'R':
                    return 'SensorImage';
                case 'S':
                    switch ($formatCode2) {
                        case 'D':
                            $size = strtoupper(substr($contents, 6, 1));
                            $material = strtoupper(substr($contents, 10, 1));
                            $soundTech = strtoupper(substr($contents, 13, 1));
                            if (
                                $soundTech == 'D'
                                || ($size == 'G' && $material == 'M')
                            ) {
                                return 'i' === $typeOfRecord ? 'NonmusicalCD' : 'CD';
                            }
                            return 'i' === $typeOfRecord ? 'NonmusicalDisc' : 'SoundDisc';
                        case 'S':
                            return 'i' === $typeOfRecord
                                ? 'NonmusicalCassette' : 'SoundCassette';
                        case 'R':
                            return 'i' === $typeOfRecord
                                ? 'NonmusicalRecordingOnline' : 'SoundRecordingOnline';
                        default:
                            if ('i' === $typeOfRecord) {
                                return 'NonmusicalRecording';
                            }
                            if ('j' === $typeOfRecord) {
                                return 'MusicRecording';
                            }
                            return $online ? 'SoundRecordingOnline' : 'SoundRecording';
                    }
                    // @phpstan-ignore-next-line
                    break;
                case 'V':
                    $videoFormat = strtoupper(substr($contents, 4, 1));
                    switch ($videoFormat) {
                        case 'S':
                            return 'BluRay';
                        case 'V':
                            return 'DVD';
                    }

                    switch ($formatCode2) {
                        case 'C':
                            return 'VideoCartridge';
                        case 'D':
                            return 'VideoDisc';
                        case 'F':
                            return 'VideoCassette';
                        case 'R':
                            return 'VideoReel';
                        case 'Z':
                            if ($online) {
                                return 'OnlineVideo';
                            }
                            return 'Video';
                        default:
                            return 'Video';
                    }
                    // @phpstan-ignore-next-line
                    break;
            }
        }

        switch (strtoupper($typeOfRecord)) {
            case 'C':
            case 'D':
                return 'MusicalScore';
            case 'E':
            case 'F':
                return 'Map';
            case 'G':
                return 'Slide';
            case 'I':
                return 'SoundRecording';
            case 'J':
                return 'MusicRecording';
            case 'K':
                return 'Photo';
            case 'M':
                return 'Electronic';
            case 'O':
            case 'P':
                return 'Kit';
            case 'R':
                return 'PhysicalObject';
            case 'T':
                return 'Manuscript';
        }

        if (!$online) {
            $online = substr($field008, 23, 1) === 'o';
        }

        switch (strtoupper($bibliographicLevel)) {
            case 'M':
                // Monograph
                if ($online) {
                    return 'eBook';
                } else {
                    return 'Book';
                }
                // @phpstan-ignore-next-line
                break;
            case 'S':
                // Serial
                // Look in 008 to determine what type of Continuing Resource
                $formatCode = strtoupper(substr($field008, 21, 1));
                switch ($formatCode) {
                    case 'N':
                        return $online ? 'eNewspaper' : 'Newspaper';
                    case 'P':
                        return $online ? 'eJournal' : 'Journal';
                    default:
                        return $online ? 'eSerial' : 'Serial';
                }
                // @phpstan-ignore-next-line
                break;
            case 'A':
                // Component part in monograph
                return $online ? 'eBookSection' : 'BookSection';
            case 'B':
                // Component part in serial
                return $online ? 'eArticle' : 'Article';
            case 'C':
                // Collection
                return 'Collection';
            case 'D':
                // Component part in collection (sub unit)
                return 'SubUnit';
            case 'I':
                // Integrating resource
                return 'ContinuouslyUpdatedResource';
        }
        return 'Other';
    }

    /**
     * Get alternate titles
     *
     * @return array
     *
     * @psalm-suppress DuplicateArrayKey
     */
    protected function getAltTitles(): array
    {
        $altTitles = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_ALT, '245', ['a', 'b']],
                [MarcHandler::GET_BOTH, '130', [
                    'a', 'd', 'f', 'g', 'h', 'k', 'l', 'n', 'p', 'r', 's',
                    't',
                ]],
                [MarcHandler::GET_BOTH, '240', [
                    'a', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 'o', 'p',
                    'r', 's',
                ]],
                [MarcHandler::GET_BOTH, '243', [
                    'a', 'd', 'f', 'g', 'h', 'k', 'l', 'm', 'n', 'o', 'p',
                    'r', 's',
                ]],
                [MarcHandler::GET_BOTH, '246', ['a', 'b', 'n', 'p']],
                // Use only 700 fields that contain subfield 't'
                [
                    MarcHandler::GET_BOTH,
                    '700',
                    [
                        't', 'm', 'r', 'h', 'i', 'g', 'n', 'p', 's', 'l',
                        'o', 'k',
                    ],
                    ['t'],
                ],
                [MarcHandler::GET_BOTH, '730', [
                    'a', 'd', 'f', 'g', 'h', 'i', 'k', 'l', 'm', 'n', 'o',
                    'p', 'r', 's', 't',
                ]],
                [MarcHandler::GET_BOTH, '740', ['a']],
                // 979b = component part title
                [MarcHandler::GET_BOTH, '979', ['b']],
                // 979e = component part uniform title
                [MarcHandler::GET_BOTH, '979', ['e']],
                // Finnish 9xx reference field
                [MarcHandler::GET_BOTH, '940', ['a']],
            ]
        );
        $altTitles = [
            ...$altTitles,
            ...array_map(
                [$this->metadataUtils, 'stripTrailingPunctuation'],
                $this->record->getFieldsSubfields('505', ['t'], null)
            ),
        ];

        return array_values(array_unique($altTitles));
    }

    /**
     * Try to determine the gaming console or other platform identifiers
     *
     * @return array
     */
    protected function getGamePlatformIds()
    {
        $result = [];
        $fields = $this->record->getFields('753');
        if ($fields) {
            foreach ($fields as $field) {
                if ($id = $this->record->getSubfield($field, '0')) {
                    $result[] = $id;
                }
                if ($os = $this->record->getSubfield($field, 'c')) {
                    $result[] = $os;
                }
                if ($device = $this->record->getSubfield($field, 'a')) {
                    $result[] = $device;
                }
            }
        } elseif ($field = $this->record->getField('245')) {
            if ($b = $this->record->getSubfield($field, 'b')) {
                $result[] = $this->metadataUtils->stripTrailingPunctuation($b);
            }
        }

        return $result;
    }

    /**
     * Return usage rights if any
     *
     * @return array ['restricted'] or a more specific id if restricted,
     * empty array otherwise
     */
    protected function getUsageRights()
    {
        $rights = [];
        foreach ($this->record->getFields('540') as $field) {
            $sub3 = $this->metadataUtils->stripTrailingPunctuation(
                $this->record->getSubfield($field, '3')
            );
            if ($sub3 == 'Metadata' || strncasecmp($sub3, 'metadata', 8) == 0) {
                continue;
            }
            $subF = $this->metadataUtils->stripTrailingPunctuation(
                $this->record->getSubfield($field, 'f')
            );
            if ($subF) {
                $rights[] = $subF;
            }
            $subC = $this->metadataUtils->stripTrailingPunctuation(
                $this->record->getSubfield($field, 'c')
            );
            if ($subC) {
                $rights[] = $subC;
            }
            $url = $this->metadataUtils->stripTrailingPunctuation(
                $this->record->getSubfield($field, 'u')
            );
            if ($url) {
                $rights[] = $url;
            }
            if (!$url && !$subC) {
                $rights[] = 'restricted';
            }
        }
        return $rights;
    }

    /**
     * Return publication year/date range
     *
     * @return array Date range
     */
    protected function getPublicationDateRange()
    {
        $field008 = $this->record->getControlField('008');
        if ($field008) {
            switch (substr($field008, 6, 1)) {
                case 'c':
                    $year = substr($field008, 7, 4);
                    $startDate = "$year-01-01T00:00:00Z";
                    $endDate = '9999-12-31T23:59:59Z';
                    break;
                case 'd':
                case 'i':
                case 'k':
                case 'm':
                case 'q':
                    $year1 = substr($field008, 7, 4);
                    $year2 = substr($field008, 11, 4);
                    if (ctype_digit($year1) && ctype_digit($year2) && $year2 < $year1) {
                        $startDate = "$year2-01-01T00:00:00Z";
                        $endDate = "$year1-12-31T23:59:59Z";
                    } else {
                        $startDate = "$year1-01-01T00:00:00Z";
                        $endDate = "$year2-12-31T23:59:59Z";
                    }
                    break;
                case 'e':
                    $year = substr($field008, 7, 4);
                    $mon = substr($field008, 11, 2);
                    $day = substr($field008, 13, 2);
                    $startDate = "$year-$mon-{$day}T00:00:00Z";
                    $endDate = "$year-$mon-{$day}T23:59:59Z";
                    break;
                case 's':
                case 't':
                case 'u':
                    $year = substr($field008, 7, 4);
                    $startDate = "$year-01-01T00:00:00Z";
                    $endDate = "$year-12-31T23:59:59Z";
                    break;
            }
        }

        if (
            !isset($startDate)
            || !isset($endDate)
            || $this->metadataUtils->validateISO8601Date($startDate) === false
            || $this->metadataUtils->validateISO8601Date($endDate) === false
        ) {
            if ($field = $this->record->getField('260')) {
                $year = $this->extractYear($this->record->getSubfield($field, 'c'));
                if ($year) {
                    $startDate = "{$year}-01-01T00:00:00Z";
                    $endDate = "{$year}-12-31T23:59:59Z";
                }
            }
        }

        if (
            !isset($startDate)
            || !isset($endDate)
            || $this->metadataUtils->validateISO8601Date($startDate) === false
            || $this->metadataUtils->validateISO8601Date($endDate) === false
        ) {
            foreach ($this->record->getFields('264') as $field) {
                if ($this->record->getIndicator($field, 2) !== '1') {
                    continue;
                }
                $publishDate = $this->record->getSubfield($field, 'c');
                if ($years = $this->extractYearRange($publishDate)) {
                    $startDate = "{$years[0]}-01-01T00:00:00Z";
                    $endDate = "{$years[1]}-12-31T23:59:59Z";
                    break;
                }
                if ($year = $this->extractYear($publishDate)) {
                    $startDate = "{$year}-01-01T00:00:00Z";
                    $endDate = "{$year}-12-31T23:59:59Z";
                    break;
                }
            }
        }
        if (
            isset($startDate)
            && isset($endDate)
            && $this->metadataUtils->validateISO8601Date($startDate) !== false
            && $this->metadataUtils->validateISO8601Date($endDate) !== false
        ) {
            if ($endDate < $startDate) {
                $this->logger->logDebug(
                    'Marc',
                    "Invalid date range {$startDate} - {$endDate}, record "
                        . "{$this->source}." . $this->getID(),
                    true
                );
                $this->storeWarning('invalid date range in 008');
                $endDate = substr($startDate, 0, 4) . '-12-31T23:59:59Z';
            }
            return [$startDate, $endDate];
        }

        return [];
    }

    /**
     * Extract a year range from a field such as publication date.
     *
     * @param string $field Field
     *
     * @return ?array [start, end] or null if no match
     */
    protected function extractYearRange($field): ?array
    {
        $subjects = [];
        // First look for years in brackets:
        if (preg_match('/\[(.+)\]/', $field, $matches)) {
            $subjects[] = $matches[1];
        }
        // Then look for any years:
        $subjects[] = $field;

        foreach ($subjects as $subject) {
            foreach ($this->publicationDateRangePatterns as $pattern) {
                if (preg_match($pattern, $subject, $years)) {
                    return [$years['begin'], $years['end']];
                }
            }
        }
        return null;
    }

    /**
     * Get 653 fields that have the requested second indicator
     *
     * @param string|array $ind Allowed second indicator value(s)
     *
     * @return array<int, string>
     */
    protected function get653WithSecondInd($ind)
    {
        $key = __METHOD__ . '-' . (is_array($ind) ? implode(',', $ind) : $ind);
        if (isset($this->resultCache[$key])) {
            return $this->resultCache[$key];
        }
        $result = [];
        $ind = (array)$ind;
        foreach ($this->record->getFields('653') as $field) {
            if (in_array($this->record->getIndicator($field, 2), $ind)) {
                $term = $this->getSubfields($field, ['a']);
                if ($term) {
                    $result[] = $term;
                }
            }
        }
        $this->resultCache[$key] = $result;
        return $result;
    }

    /**
     * Get an array of all fields relevant to allfields search
     *
     * @return array
     */
    protected function getAllFields(): array
    {
        $fieldFilter = [
            '300' => 1, '336' => 1, '337' => 1, '338' => 1,
        ];
        $excludedSubfields = [
            '015' => ['q', 'z', '2', '6', '8'],
            '024' => ['c', 'd', 'z', '6', '8'],
            '027' => ['z', '6', '8'],
            '031' => [
                'a', 'b', 'c', 'd', 'e', 'g', 'm',
                'n', 'o', 'p', 'q', 'r', 's', 'u',
                'y', 'z', '2', '6', '8',
            ],
            '650' => ['0', '2', '6', '8'],
            '690' => ['0', '2', '6', '8'],
            '100' => ['0', '4'],
            '700' => ['0', '4'],
            '710' => ['0', '4'],
            '711' => ['0', '4'],
            '773' => [
                '0', '4', '6', '7', '8', 'g', 'q',
                'w',
            ],
            '787' => ['i'],
            // Koha serial enumerations
            '952' => ['a', 'b', 'c', 'o'],
            '979' => ['0', 'a', 'f'],
        ];
        $allFields = [];
        // Include ISBNs, also normalized if possible
        foreach ($this->isbnFields as $fieldSpec) {
            foreach ($this->getFieldsSubfields($fieldSpec['selector'], false, true, true) as $isbn) {
                if (strlen($isbn) < 10) {
                    continue;
                }
                $allFields[] = $isbn;
                $normalized = $this->metadataUtils->normalizeISBN($isbn);
                if ($normalized && $normalized !== $isbn) {
                    $allFields[] = $normalized;
                }
            }
        }
        foreach ($this->record->getAllFields() as $field) {
            $tag = $field['tag'];
            if (
                ($tag >= 100 && $tag < 841 && !isset($fieldFilter[$tag]))
                || in_array(
                    $tag,
                    [
                        '015', '024', '025', '026', '027', '028', '031',
                        // Finnish field:
                        '509',
                        '880',
                        // Finnish fields:
                        '900',
                        '910',
                        '911',
                        '920',
                        '940',
                        '952',
                        // Component parts:
                        '979',
                    ]
                )
            ) {
                $subfields = $this->getAllSubfields(
                    $field,
                    $excludedSubfields[$tag] ?? ['0', '6', '8']
                );
                if ($subfields) {
                    $allFields = [...$allFields, ...$subfields];
                }
            }
        }
        $allFields = array_map(
            function ($str) {
                return $this->metadataUtils->stripTrailingPunctuation(
                    $this->metadataUtils->stripLeadingPunctuation($str)
                );
            },
            $allFields
        );
        return array_values(array_unique($allFields));
    }

    /**
     * Get the building field
     *
     * @return array<int, string>
     */
    protected function getBuilding()
    {
        $building = parent::getBuilding();

        // Ebrary location
        $ebraryLocs = $this->getFieldsSubfields(
            [[MarcHandler::GET_NORMAL, '035', ['a']]]
        );
        foreach ($ebraryLocs as $field) {
            if (str_starts_with($field, 'ebr') && is_numeric(substr($field, 3))) {
                if (!in_array('EbraryDynamic', $building)) {
                    $building[] = 'EbraryDynamic';
                }
            }
        }

        return $building;
    }

    /**
     * Get default fields used to populate the building field
     *
     * @return array
     */
    protected function getDefaultBuildingFields()
    {
        $useSub = $this->getDriverParam('subLocationInBuilding', '');
        $itemSub = $this->getDriverParam('itemSubLocationInBuilding', $useSub);
        return [
            [
                'field' => '852',
                'loc' => 'b',
                'sub' => $useSub,
            ],
            [
                'field' => '952',
                'loc' => 'b',
                'sub' => $itemSub,
            ],
        ];
    }

    /**
     * Get era facet fields
     *
     * @return array<int, string> Topics
     */
    protected function getEraFacets()
    {
        $result = parent::getEraFacets();
        $result = [
            ...$result,
            ...$this->getAdditionalEraFields(),
        ];
        return $result;
    }

    /**
     * Get all era topics
     *
     * @return array<int, string>
     */
    protected function getEras()
    {
        $result = parent::getEras();
        $result = [
            ...$result,
            ...$this->getAdditionalEraFields(),
        ];
        return $result;
    }

    /**
     * Get additional era fields
     *
     * @return array<int, string>
     */
    protected function getAdditionalEraFields()
    {
        if (!isset($this->resultCache[__METHOD__])) {
            $this->resultCache[__METHOD__] = [
                ...$this->get653WithSecondInd('4'),
                ...$this->getFieldsSubfields(
                    [[MarcHandler::GET_NORMAL, '388', ['a']]]
                ),
            ];
        }
        return $this->resultCache[__METHOD__];
    }

    /**
     * Get genre facet fields
     *
     * @return array<int, string> Topics
     */
    protected function getGenreFacets()
    {
        $result = parent::getGenreFacets();
        $result = [
            ...$result,
            ...$this->get653WithSecondInd('6'),
        ];
        return $result;
    }

    /**
     * Get all genre topics
     *
     * @return array<int, string>
     */
    protected function getGenres()
    {
        $result = parent::getGenres();
        $result = [
            ...$result,
            ...$this->get653WithSecondInd('6'),
        ];
        return $result;
    }

    /**
     * Get geographic facet fields
     *
     * @return array<int, string> Topics
     */
    protected function getGeographicFacets()
    {
        $result = parent::getGeographicFacets();
        $result = [
            ...$result,
            ...$this->get653WithSecondInd('5'),
            ...$this->getFieldsSubfields([[MarcHandler::GET_NORMAL, '370', ['g']]]),
        ];
        return $result;
    }

    /**
     * Get all geographic topics
     *
     * @return array<int, string>
     */
    protected function getGeographicTopics()
    {
        $result = parent::getGeographicTopics();
        $result = [
            ...$result,
            ...$this->get653WithSecondInd('5'),
            ...$this->getFieldsSubfields([[MarcHandler::GET_NORMAL, '370', ['g']]]),
        ];
        return $result;
    }

    /**
     * Get all geographic topic identifiers
     *
     * @return array<int, string>
     */
    protected function getGeographicTopicIDs()
    {
        $result = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '651', ['0']],
            ]
        );
        return $this->addNamespaceToAuthorityIds($result, 'geographic');
    }

    /**
     * Get topic facet fields
     *
     * @return array<int, string> Topics
     */
    protected function getTopicFacets()
    {
        $result = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '567', ['b']],
                [MarcHandler::GET_NORMAL, '600', ['a', 'x']],
                [MarcHandler::GET_NORMAL, '610', ['a', 'x']],
                [MarcHandler::GET_NORMAL, '611', ['a', 'x']],
                [MarcHandler::GET_NORMAL, '630', ['a', 'x']],
                [MarcHandler::GET_NORMAL, '648', ['x']],
                [MarcHandler::GET_NORMAL, '650', ['a', 'x']],
                [MarcHandler::GET_NORMAL, '651', ['x']],
                [MarcHandler::GET_NORMAL, '655', ['x']],
                [MarcHandler::GET_NORMAL, '690', ['a', 'x']],
                [MarcHandler::GET_NORMAL, '385', ['a']],
                [MarcHandler::GET_NORMAL, '386', ['a']],
            ],
            false,
            true,
            true
        );
        $result = [
            ...$result,
            ...$this->get653WithSecondInd([' ', '0', '1', '2', '3']),
        ];
        return $result;
    }

    /**
     * Get all non-specific topics
     *
     * @return array<int, string>
     */
    protected function getTopics()
    {
        $result = [
            ...parent::getTopics(),
            ...$this->get653WithSecondInd([' ', '0', '1', '2', '3']),
            ...$this->getFieldsSubfields(
                [
                    [MarcHandler::GET_NORMAL, '385', ['a']],
                    [MarcHandler::GET_NORMAL, '356', ['a']],
                    [MarcHandler::GET_NORMAL, '567', ['b']],
                    [MarcHandler::GET_BOTH, '690', [
                        'a', 'b', 'c', 'd', 'e', 'v', 'x', 'y', 'z',
                    ]],
                ]
            ),
        ];
        return $result;
    }

    /**
     * Get all language codes
     *
     * @return array<int, string> Language codes
     */
    protected function getLanguages()
    {
        $languages = [substr($this->record->getControlField('008'), 35, 3)];
        $languages2 = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '041', ['a']],
                [MarcHandler::GET_NORMAL, '041', ['d']],
                // 979h = component part language
                [MarcHandler::GET_NORMAL, '979', ['h']],
            ],
            false,
            true,
            true
        );
        $result = [...$languages, ...$languages2];
        return $this->metadataUtils->normalizeLanguageStrings($result);
    }

    /**
     * Get primary authors
     *
     * @return array
     */
    protected function getPrimaryAuthors()
    {
        $fieldSpecs = [
            '100' => ['a', 'b', 'c', 'd', 'e'],
            '700' => [
                'a', 'q', 'b', 'c', 'd', 'e',
            ],
        ];
        return $this->getAuthorsByRelator(
            $fieldSpecs,
            $this->primaryAuthorRelators,
            ['100']
        );
    }

    /**
     * Get primary authors for faceting
     *
     * @return array
     */
    protected function getPrimaryAuthorsFacet()
    {
        $fieldSpecs = [
            '100' => ['a', 'b', 'c'],
            '700' => [
                'a', 'q', 'b', 'c',
            ],
        ];
        return $this->getAuthorsByRelator(
            $fieldSpecs,
            $this->primaryAuthorRelators,
            ['100'],
            false
        );
    }

    /**
     * Get secondary authors
     *
     * @return array
     */
    protected function getSecondaryAuthors()
    {
        $fieldSpecs = [
            '100' => ['a', 'b', 'c', 'd', 'e'],
            '700' => [
                'a', 'q', 'b', 'c', 'd', 'e',
            ],
        ];
        return $this->getAuthorsByRelator(
            $fieldSpecs,
            $this->primaryAuthorRelators,
            ['100'],
            true,
            true
        );
    }

    /**
     * Get secondary authors for faceting
     *
     * @return array
     */
    protected function getSecondaryAuthorsFacet()
    {
        $fieldSpecs = [
            '100' => ['a', 'b', 'c'],
            '700' => [
                'a', 'q', 'b', 'c',
            ],
        ];
        return $this->getAuthorsByRelator(
            $fieldSpecs,
            $this->primaryAuthorRelators,
            ['100'],
            false,
            true
        );
    }

    /**
     * Get corporate authors
     *
     * @return array
     */
    protected function getCorporateAuthors()
    {
        $fieldSpecs = [
            '110' => ['a', 'b', 'e'],
            '111' => ['a', 'b', 'e'],
            '710' => ['a', 'b', 'e'],
            '711' => ['a', 'b', 'e'],
        ];
        return $this->getAuthorsByRelator(
            $fieldSpecs,
            [],
            ['110', '111', '710', '711'],
            false
        );
    }

    /**
     * Get corporate authors for faceting
     *
     * @return array
     */
    protected function getCorporateAuthorsFacet()
    {
        $fieldSpecs = [
            '110' => ['a', 'b'],
            '111' => ['a', 'b'],
            '710' => ['a', 'b'],
            '711' => ['a', 'b'],
        ];
        return $this->getAuthorsByRelator(
            $fieldSpecs,
            [],
            ['110', '111', '710', '711']
        );
    }

    /**
     * Get locations for available items (from Koha 952 fields)
     *
     * @return array
     */
    protected function getAvailableItemsBuildings()
    {
        $buildingSubfields = [];
        foreach ($this->getBuildingFieldSpec() as $spec) {
            if ('952' === $spec['field']) {
                $buildingSubfields[] = $spec;
            }
        }
        $building = [];
        if ($this->getDriverParam('holdingsInBuilding', true)) {
            foreach ($this->record->getFields('952') as $field) {
                $available = $this->record->getSubfield($field, '9');
                if (!$available) {
                    continue;
                }
                foreach ($buildingSubfields as $buildingField) {
                    $location = $this->record->getSubfield($field, $buildingField['loc']);
                    if ($location) {
                        $subLocField = $buildingField['sub'];
                        if ($subLocField) {
                            $sub = $this->record->getSubfield($field, $subLocField);
                            if ($sub) {
                                $location = [$location, $sub];
                            }
                        }
                        $building[] = $location;
                    }
                }
            }
        }
        return $building;
    }

    /**
     * Get original languages in normalized form
     *
     * @return array
     */
    protected function getOriginalLanguages()
    {
        // 041h - Language code of original
        $languages = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '041', ['h']],
                // 979i = component part original language
                [MarcHandler::GET_NORMAL, '979', ['i']],
            ],
            false,
            true,
            true
        );
        // If not a translation, take also language from 041a and 041d.
        foreach ($this->record->getFields('041') as $f041) {
            if ($this->record->getIndicator($f041, 1) === '0') {
                foreach ($this->getSubfieldsArray($f041, ['a', 'd']) as $s) {
                    $languages[] = $s;
                }
            }
        }
        return $this->metadataUtils->normalizeLanguageStrings($languages);
    }

    /**
     * Get subtitle languages in normalized form
     *
     * @return array
     */
    protected function getSubtitleLanguages()
    {
        $languages = $this->getFieldsSubfields(
            [
                [MarcHandler::GET_NORMAL, '041', ['j']],
                // 979j = component part subtitle language
                [MarcHandler::GET_NORMAL, '979', ['j']],
            ],
            false,
            true,
            true
        );
        return $this->metadataUtils->normalizeLanguageStrings($languages);
    }

    /**
     * Get series information
     *
     * @return array
     */
    protected function getSeries()
    {
        return $this->getFieldsSubfields(
            [
                [MarcHandler::GET_BOTH, '440', ['a']],
                [MarcHandler::GET_BOTH, '490', ['a']],
                [MarcHandler::GET_BOTH, '800', [
                    'a', 'b', 'c', 'd', 'f', 'p', 'q', 't',
                ]],
                [MarcHandler::GET_BOTH, '830', ['a', 'v', 'n', 'p']],
            ]
        );
    }

    /**
     * Get link data (url and link text)
     *
     * @return array
     */
    protected function getLinkData(): array
    {
        if (isset($this->resultCache[__FUNCTION__])) {
            return $this->resultCache[__FUNCTION__];
        }

        $results = [];
        $fields = $this->record->getFields('856');
        foreach ($fields as $field) {
            if ($this->record->getSubfield($field, '3')) {
                continue;
            }
            $ind2 = $this->record->getIndicator($field, 2);
            if (($ind2 != '0' && $ind2 != '1')) {
                continue;
            }
            $url = trim($this->record->getSubfield($field, 'u'));
            if (!$url) {
                continue;
            }
            // Require at least one dot surrounded by valid characters or a
            // familiar scheme
            if (
                !preg_match('/[A-Za-z0-9]\.[A-Za-z0-9]/', $url)
                && !preg_match('/^https?:\/\//', $url)
            ) {
                continue;
            }
            $result = [
                'url' => $url,
            ];
            $text = $this->record->getSubfield($field, 'y');
            if (!$text) {
                $text = $this->record->getSubfield($field, 'z');
            }
            $result['text'] = $text;
            $mediaType = $this->getLinkMediaType(
                $url,
                $this->record->getSubfield($field, 'q')
            );
            if ($mediaType) {
                $result['mediaType'] = $mediaType;
            }
            $results[] = $result;
        }

        $this->resultCache[__FUNCTION__] = $results;
        return $results;
    }

    /**
     * Check if the record is available online
     *
     * @return bool
     */
    protected function isOnline(): bool
    {
        if (null !== ($online = $this->getDriverParam('online', null))) {
            return boolval($online);
        }

        if ($this->getLinkData()) {
            return true;
        }

        // Check online availability from carrier type.
        foreach ($this->record->getFields('338') as $field) {
            if ('cr' === $this->record->getSubfield($field, 'b')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the record is freely available online
     *
     * @return bool
     */
    protected function isFreeOnline(): bool
    {
        if (null !== ($free = $this->getDriverParam('freeOnline', null))) {
            return boolval($free);
        }

        $access = $this->metadataUtils->normalizeKey(
            $this->getFieldSubfields('506', ['f']),
            'NFKC'
        );
        // Require link data (otherwise records with just 'cr' in 338b are marked
        // available as well):
        if (!$access && !$this->getLinkData()) {
            return false;
        }

        return $access !== 'onlineaccesswithauthorization';
    }

    /**
     * Get extra classifications based on driver params
     *
     * @return array
     *
     * @psalm-suppress DuplicateArrayKey
     */
    protected function getExtraClassifications(): array
    {
        if (!($extraFields = $this->getDriverParam('classifications', false))) {
            return [];
        }

        $result = [];
        foreach (explode(':', $extraFields) as $classSpec) {
            $parts = explode('=', $classSpec);
            $fieldSpec = $parts[0];
            $prefix = $parts[1] ?? '';

            $field = substr($fieldSpec, 0, 3);
            $subfields = str_split(substr($fieldSpec, 3));
            $fields = $this->record->getFieldsSubfields($field, $subfields);
            if (!$fields) {
                continue;
            }
            // Make sure there is a single space between subfields:
            $fields = preg_replace('/\s{2,}/', ' ', $fields);
            if ($prefix) {
                $fields = array_map(
                    function ($s) use ($prefix) {
                        return "$prefix $s";
                    },
                    $fields
                );
            }
            $result = [
                ...$result,
                ...$fields,
            ];
        }

        return $result;
    }

    /**
     * Serialize full record to a string
     *
     * @return string
     */
    protected function getFullRecord(): string
    {
        $record = clone $this->record;
        // Filter out any order or item count summary fields:
        $record->filterFields(
            function ($field) {
                if ('852' !== (string)key($field)) {
                    return true;
                }
                $field = current($field);
                foreach ($field['subfields'] ?? [] as $subfield) {
                    if ('9' === (string)key($subfield) && in_array(current($subfield), ['items', 'orders'])) {
                        return false;
                    }
                }
                return true;
            }
        );

        $format = $this->config['MarcRecord']['solr_serialization'] ?? 'JSON';
        $result = $record->toFormat($format);
        if (!$result && 'ISO2709' === $format) {
            // If the record exceeds 99999 bytes, it doesn't fit into ISO 2709, so
            // use MARCXML as a fallback:
            $result = $this->record->toFormat('MARCXML');
        }
        return $result;
    }
}
