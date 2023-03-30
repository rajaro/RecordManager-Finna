<?php
/**
 * EAD 3 Record Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2012-2020.
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
 * @author   Jukka Lehmus <jlehmus@mappi.helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */
namespace RecordManager\Finna\Record;

use RecordManager\Base\Database\DatabaseInterface as Database;

/**
 * EAD 3 Record Class
 *
 * EAD 3 records with Finna specific functionality
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Jukka Lehmus <jlehmus@mappi.helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */
class Ead3 extends \RecordManager\Base\Record\Ead3
{
    use AuthoritySupportTrait;
    use DateSupportTrait;

    // These are always lowercase:
    public const GEOGRAPHIC_SUBJECT_RELATORS = ['aihe', 'alueellinen kattavuus'];
    public const SUBJECT_RELATORS = ['aihe', 'asiasana'];

    public const RELATOR_TIME_INTERVAL = 'suhteen ajallinen kattavuus';

    public const NAME_TYPE_VARIANT = 'varianttinimi';
    public const NAME_TYPE_ALTERNATIVE = 'vaihtehtoinen nimi';
    public const NAME_TYPE_PRIMARY = 'ensisijainen nimi';
    public const NAME_TYPE_OUTDATED = 'vanhentunut nimi';

    /**
     * Archive fonds format
     *
     * @return string
     */
    protected $fondsType = 'Document/Arkisto';

    /**
     * Archive collection format
     *
     * @return string
     */
    protected $collectionType = 'Document/Kokoelma';

    /**
     * Undefined format type
     *
     * @return string
     */
    protected $undefinedType = 'Määrittämätön';

    /**
     * Return fields to be indexed in Solr
     *
     * @param Database $db Database connection. Omit to avoid database lookups for
     *                     related records.
     *
     * @return array<string, string|array<int, string>>
     */
    public function toSolrArray(Database $db = null)
    {
        $data = parent::toSolrArray($db);
        $doc = $this->doc;

        $first = true;
        foreach ($this->getDateRanges() as $unitDateRange) {
            $range = $unitDateRange['date'];
            $data['search_daterange_mv'][] = $data['unit_daterange']
                = $this->dateRangeToStr($range);

            if ($first) {
                $data['main_date_str'] = $data['era_facet']
                    = $this->metadataUtils->extractYear($range[0]);
                $data['main_date'] = $this->validateDate($range[0]);
                $data = $this->enrichTitlesWithYearRanges($data, $unitDateRange);
                $first = false;
            }
        }

        // Single-valued sequence for sorting
        if (isset($data['hierarchy_sequence'])) {
            $data['hierarchy_sequence_str'] = $data['hierarchy_sequence'];
        }

        $data['source_str_mv'] = ($data['institution'] ?? '') ?: $this->source;
        $data['datasource_str_mv'] = $this->source;

        if ($this->isOnline()) {
            $data['online_boolean'] = '1';
            // This is sort of special. Make sure to use source instead
            // of datasource.
            $data['online_str_mv'] = $data['source_str_mv'];

            if ($this->isFreeOnline()) {
                $data['free_online_boolean'] = '1';
                // This is sort of special. Make sure to use source instead
                // of datasource.
                $data['free_online_str_mv'] = $data['source_str_mv'];
            }
        }

        if ($identifier = $this->getUnitId()) {
            $p = strpos($identifier, '/');
            $identifier = $p > 0
                ? substr($identifier, $p + 1)
                : $identifier;
            $data['identifier'] = $identifier;
        }

        if (isset($doc->did->dimensions)) {
            // display measurements
            $data['measurements'] = (string)$doc->did->dimensions;
        }

        if (isset($doc->did->physdesc)) {
            $material = [];
            foreach ($doc->did->physdesc as $physdesc) {
                if (isset($physdesc->attributes()->label)) {
                    $material[] = (string)$physdesc . ' '
                        . $physdesc->attributes()->label;
                } else {
                    $material[] = (string)$physdesc;
                }
            }
            $data['material'] = $material;
        }

        if (isset($doc->did->userestrict->p)) {
            $data['rights'] = (string)$doc->did->userestrict->p;
        } elseif (isset($doc->did->accessrestrict->p)) {
            $data['rights'] = (string)$doc->did->accessrestrict->p;
        }

        // Usage rights
        if ($rights = $this->getUsageRights()) {
            $data['usage_rights_str_mv'] = $rights;
            $data['usage_rights_ext_str_mv'] = $rights;
        }

        $corporateAuthorIds = $this->getCorporateAuthorIds();
        if (isset($doc->controlaccess->name)) {
            $data['author'] = [];
            $data['author_role'] = [];
            $data['author_variant'] = [];
            $data['author_facet'] = [];
            $author2Ids = $author2IdRoles = [];
            foreach ($doc->controlaccess->name as $name) {
                foreach ($name->part as $part) {
                    $id = $role = null;
                    $attr = $name->attributes();
                    if (isset($attr->relator)) {
                        $role = (string)$name->attributes()->relator;
                    }
                    if (isset($attr->identifier)) {
                        $id = (string)$name->attributes()->identifier;
                    }

                    $localtype = mb_strtolower(
                        $part->attributes()->localtype ?? '',
                        'UTF-8'
                    );
                    switch ($localtype) {
                    case self::NAME_TYPE_PRIMARY:
                        $data['author'][] = (string)$part;
                        if (! isset($part->attributes()->lang)
                            || (string)$part->attributes()->lang === 'fin'
                        ) {
                            $data['author_facet'][] = (string)$part;
                        }
                        if ($id) {
                            $author2Ids[] = $id;
                            if ($role) {
                                $author2IdRoles[]
                                    = $this->formatAuthorIdWithRole($id, $role);
                            }
                        }
                        break;
                    case self::NAME_TYPE_VARIANT:
                    case self::NAME_TYPE_ALTERNATIVE:
                    case self::NAME_TYPE_OUTDATED:
                        $data['author_variant'][] = (string)$part;
                        if ($id) {
                            $author2Ids[] = $id;
                            if ($role) {
                                $author2IdRoles[]
                                    = $this->formatAuthorIdWithRole($id, $role);
                            }
                        }

                        break;
                    }
                }
            }

            $data['author2_id_str_mv']
                = $this->addNamespaceToAuthorityIds(
                    array_unique([...$corporateAuthorIds, ...$author2Ids]),
                    'author'
                );
            $data['author2_id_role_str_mv']
                = $this->addNamespaceToAuthorityIds($author2IdRoles, 'author');
        }

        if (isset($doc->controlaccess->persname)) {
            foreach ($doc->controlaccess->persname as $name) {
                if (isset($name->part)) {
                    $name = (string)$name->part;
                    $data['author'][] = $name;
                    $data['author_facet'][] = $name;
                }
            }
        }

        foreach ($this->doc->did->origination ?? [] as $origination) {
            foreach ($origination->persname ?? [] as $name) {
                $data['author'][] = $data['author_facet'][] = (string)$name;
            }
        }
        foreach ($this->doc->did->origination ?? [] as $origination) {
            foreach ($origination->name ?? [] as $name) {
                foreach ($name->part ?? [] as $part) {
                    if ($this->isTimeIntervalNode($part)) {
                        continue;
                    }
                    $value = (string)$part;
                    $data['author'][] = $data['author_facet'][] = $value;
                    if (in_array(
                        (string)$part->attributes()->localtype,
                        [self::NAME_TYPE_VARIANT, self::NAME_TYPE_ALTERNATIVE]
                    )
                    ) {
                        $data['author_variant'][] = $value;
                    }
                }
            }
        }

        if (isset($doc->index->index->indexentry)) {
            foreach ($doc->index->index->indexentry as $indexentry) {
                if (isset($indexentry->name->part)) {
                    $data['contents'][] = (string)$indexentry->name->part;
                }
            }
        }
        $data['format_ext_str_mv'] = $data['format'];

        $data['topic_id_str_mv'] = $this->getTopicIDs();
        $data['geographic_id_str_mv'] = $this->getGeographicTopicIDs();

        return $data;
    }

    /**
     * Enrich titles with year ranges.
     *
     * @param array $data          Record as a solr array
     * @param array $unitDateRange Date range to append as years
     *
     * @return array
     */
    protected function enrichTitlesWithYearRanges(
        array $data,
        array $unitDateRange
    ): array {
        /**
         * Type of enrichment for driver.
         * always = Always add year range to end of a title
         * never = Do not add year range to end of a title
         * no_year_exists = If any year is found from the title then do not add
         * no_match_exists = If any of the given years in unitDateRange are
         * found from the title, then do not add
         * no_matches_exist = If all of the given years in unitDateRange are
         * found from the title, then do not add
         */
        $type = $this->getDriverParam(
            'enrichTitleWithYearRange',
            'no_match_exists'
        );
        if ('never' === $type) {
            return $data;
        }
        if (!$unitDateRange['startDateUnknown']) {
            $range = $unitDateRange['date'];
            $startYear
                = $this->metadataUtils->extractYear($range[0]);
            $endYear = $this->metadataUtils->extractYear($range[1]);
            $yearRange[] = $startYear !== '-9999' ? $startYear : '';
            $yearRange[] = $endYear !== '9999' ? $endYear : '';
            if ($yearRange[1] === '2029' && $unitDateRange['endDateUncertain']) {
                $yearRange[1] = '';
            }
            $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
            $yearRangeStr = trim(implode($ndash, array_unique($yearRange)));
            if (!$yearRangeStr) {
                return $data;
            }
            $yearRangeStr = " ($yearRangeStr)";
            foreach (
                ['title_full', 'title_sort', 'title', 'title_short']
                as $field
            ) {
                $yearsFound = $this->getYearsFromString($data[$field]);
                switch ($type) {
                case 'always':
                    $data[$field] .= $yearRangeStr;
                    break;
                case 'no_year_exists':
                    if (!$yearsFound) {
                        $data[$field] .= $yearRangeStr;
                    }
                    break;
                case 'no_match_exists':
                    if (!array_intersect($yearRange, $yearsFound)) {
                        $data[$field] .= $yearRangeStr;
                    }
                    break;
                case 'no_matches_exist':
                    $yearRange = array_filter(array_unique($yearRange));
                    if (array_intersect($yearRange, $yearsFound) !== $yearRange) {
                        $data[$field] .= $yearRangeStr;
                    }
                    break;
                }
            }
        }
        return $data;
    }

    /**
     * Get unit id
     *
     * @return string
     */
    protected function getUnitId()
    {
        $unitIdLabel = $this->getDriverParam('unitIdLabel', null);
        $firstId = '';
        if (isset($this->doc->did->unitid)) {
            foreach ($this->doc->did->unitid as $i) {
                $attr = $i->attributes();
                if (!isset($attr->identifier)) {
                    continue;
                }
                $id = (string)$attr->identifier;
                if (!$firstId) {
                    $firstId = $id;
                }
                if (!$unitIdLabel || (string)$attr->label === $unitIdLabel) {
                    return $id;
                }
            }
        }
        return $firstId;
    }

    /**
     * Get authors
     *
     * @return array
     */
    protected function getAuthors()
    {
        $result = [];
        if (!isset($this->doc->relations->relation)) {
            return $result;
        }

        foreach ($this->doc->relations->relation as $relation) {
            $type = (string)$relation->attributes()->relationtype;
            if ('cpfrelation' !== $type) {
                continue;
            }
            $role = (string)$relation->attributes()->arcrole;
            switch ($role) {
            case '':
            case 'http://www.rdaregistry.info/Elements/u/P60672':
            case 'http://www.rdaregistry.info/Elements/u/P60434':
                $role = 'aut';
                break;
            case 'http://www.rdaregistry.info/Elements/u/P60429':
                $role = 'pht';
                break;
            default:
                $role = '';
            }
            if ('' === $role) {
                continue;
            }
            $result[] = trim((string)$relation->relationentry);
        }
        return $result;
    }

    /**
     * Get author identifiers
     *
     * @return array
     */
    protected function getAuthorIds()
    {
        $result = [];
        if (!isset($this->doc->relations->relation)) {
            return $result;
        }

        foreach ($this->doc->relations->relation as $relation) {
            $type = (string)$relation->attributes()->relationtype;
            if ('cpfrelation' !== $type) {
                continue;
            }
            $role = (string)$relation->attributes()->arcrole;
            switch ($role) {
            case '':
            case 'http://www.rdaregistry.info/Elements/u/P60672':
            case 'http://www.rdaregistry.info/Elements/u/P60434':
                $role = 'aut';
                break;
            case 'http://www.rdaregistry.info/Elements/u/P60429':
                $role = 'pht';
                break;
            default:
                $role = '';
            }
            if ('' === $role) {
                continue;
            }
            $result[] = (string)$relation->attributes()->href;
        }
        return $result;
    }

    /**
     * Get corporate authors
     *
     * @return array<int, string>
     */
    protected function getCorporateAuthors() : array
    {
        $result = [];
        foreach ($this->doc->controlaccess->corpname ?? [] as $name) {
            foreach ($name->part ?? [] as $part) {
                if ($this->isTimeIntervalNode($part)) {
                    continue;
                }
                $result[] = trim((string)$part);
            }
        }
        foreach ($this->doc->did->origination ?? [] as $origination) {
            foreach ($origination->name ?? [] as $name) {
                foreach ($name->part ?? [] as $part) {
                    if ($this->isTimeIntervalNode($part)) {
                        continue;
                    }
                    $result[] = trim((string)$part);
                }
            }
        }
        return $result;
    }

    /**
     * Get corporate author identifiers
     *
     * @return array<int, string>
     */
    protected function getCorporateAuthorIds()
    {
        $result = [];
        if (isset($this->doc->did->origination->name)) {
            foreach ($this->doc->did->origination->name as $name) {
                if (isset($name->attributes()->identifier)) {
                    $result[] = (string)$name->attributes()->identifier;
                }
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
        $getRestriction = function ($restrict) {
            if ($href = $restrict['href']) {
                if (preg_match('/^https?:\/\/creativecommons\.org\//', $href)
                    || preg_match('/^https?:\/\/rightsstatements\.org\//', $href)
                ) {
                    return [(string)$href];
                }
            }
            $restrict = (string)$restrict;
            if (strstr($restrict, 'No known copyright restrictions')) {
                return ['No known copyright restrictions'];
            }
            if (strncasecmp($restrict, 'CC', 2) === 0
                || strncasecmp($restrict, 'Public', 6) === 0
                || strncasecmp($restrict, 'Julkinen', 8) === 0
            ) {
                return [$restrict];
            }
            return null;
        };

        // Handle each element separately. Any merging as an array is bound to cause
        // problems with element attributes.
        $nonRefRestrict = null;
        foreach ($this->doc->userestrict ?? [] as $userestrict) {
            foreach ($userestrict->p ?? [] as $p) {
                // Use ref as the primary source with contents of the p as a
                // fallback:
                foreach ($p->ref as $ref) {
                    if ($result = $getRestriction($ref)) {
                        return $result;
                    }
                }
                if (null === $nonRefRestrict) {
                    $nonRefRestrict = $getRestriction($p);
                }
            }
        }
        if ($nonRefRestrict) {
            return $nonRefRestrict;
        }
        foreach ($this->doc->accessrestrict ?? [] as $accessrestrict) {
            foreach ($accessrestrict->p ?? [] as $p) {
                if ($result = $getRestriction($p)) {
                    return $result;
                }
            }
        }

        return ['restricted'];
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
        foreach ($this->doc->did->daoset->dao ?? [] as $dao) {
            if ($dao->attributes()->{'href'}) {
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
        foreach ($this->doc->accessrestrict->p ?? [] as $restrict) {
            if (trim((string)$restrict) !== '') {
                return false;
            }
        }
        return $this->getDriverParam('freeOnlineDefault', true);
    }

    /**
     * Return subtitle
     *
     * @return string
     */
    protected function getSubtitle()
    {
        $noSubtitleFormats = [
            $this->fondsType,
            $this->collectionType
        ];
        if (in_array($this->getFormat(), $noSubtitleFormats)) {
            return '';
        }
        if ($signumLabel = $this->getDriverParam('signumLabel', null)) {
            if (isset($this->doc->did->unitid)) {
                foreach ($this->doc->did->unitid as $id) {
                    $attr = $id->attributes();
                    if ((string)$attr->label === $signumLabel) {
                        return (string)$id;
                    }
                }
            }
        }
        return (string)$this->doc->did->unitid;
    }

    /**
     * Get date ranges
     *
     * @return array
     */
    protected function getDateRanges()
    {
        $result = [];
        foreach ($this->doc->did->unitdatestructured ?? [] as $date) {
            if (isset($date->daterange)) {
                $range = $this->doc->did->unitdatestructured->daterange;
                if (isset($range->fromdate) && isset($range->todate)) {
                    // Some data sources have multiple ranges in one daterange
                    // (non-standard presentation), try to handle the case sensibly:
                    $toDate = (string)$range->fromdate;
                    foreach ($range->todate as $to) {
                        $toDate = (string)$to;
                    }
                    $result[] = $this->parseDateRange(
                        (string)$range->fromdate . '/' . $toDate
                    );
                }
            } elseif (isset($date->datesingle)) {
                $year = (string)$date->datesingle;
                $result[] = $this->parseDateRange("{$year}/{$year}");
            }
        }
        if (!$result) {
            $primary = [];
            foreach ($this->doc->did->unitdate ?? [] as $unitdate) {
                $attributes = $unitdate->attributes();
                if ($attributes->label
                    && (string)$attributes->label === 'Ajallinen kattavuus'
                    && $unitdate->attributes()->normal
                ) {
                    $date = $this->parseDateRange(
                        (string)$unitdate->attributes()->normal
                    );
                    if ($date
                        && !$date['startDateUnknown'] && !$date['endDateUnknown']
                    ) {
                        $primary[] = $date;
                        continue;
                    }
                }
                $normal = (string)$unitdate->attributes()->normal;
                if (!empty($normal)) {
                    $result[] = $this->parseDateRange($normal);
                } else {
                    foreach (explode(', ', (string)$unitdate) as $single) {
                        $date = str_replace('-', '/', $single);
                        if (false === strpos($date, '/')) {
                            $date = "${date}/${date}";
                        }
                        $result[] = $this->parseDateRange($date);
                    }
                }
            }
            if ($primary) {
                $result = [...$primary, ...$result];
            }
        }
        return array_filter($result);
    }

    /**
     * Parse date range string
     *
     * @param string $input Date range
     *
     * @return null|array
     */
    protected function parseDateRange($input)
    {
        if (!$input || $input == '-' || false === strpos($input, '/')) {
            return null;
        }

        [$start, $end] = explode('/', $input);

        $parseDate = function (
            $date,
            $defaultYear = '0',
            $defaultMonth = '01',
            $defaultDay = '01',
            $hour = '00:00:00'
        ) {
            $unknown = false;
            $uncertain = false;
            // Set year/month/day to defaults
            $year = str_repeat($defaultYear, 4);
            $month = $defaultMonth;
            $day = $defaultDay;
            if (!in_array($date, ['open', 'unknown'])) {
                $parts = explode('-', trim($date));
                if ('uuuu' === $parts[0]) {
                    $unknown = true;
                }
                if (strstr($parts[0], 'u')) {
                    $uncertain = true;
                }
                $year = str_replace('u', $defaultYear, $parts[0]);

                if (isset($parts[1]) && $parts[1] !== 'uu') {
                    $month = $parts[1];
                }

                if (isset($parts[2]) && $parts[2] !== 'uu') {
                    $day = $parts[2];
                }
            } else {
                $unknown = true;
            }

            if (null === $day) {
                // Set day to last day of month if default day was not given
                $day = date('t', strtotime("{$year}-{$month}"));
            }

            if (!preg_match('/^-?\d{1,4}$/', $year)
                || !preg_match('/^\d{1,2}$/', $month)
                || !preg_match('/^\d{1,2}$/', $day)
            ) {
                return null;
            }

            $date = sprintf(
                '%04d-%02d-%02dT%sZ',
                $year,
                $month,
                $day,
                $hour
            );

            try {
                $d = new \DateTime($date);
            } catch (\Exception $e) {
                return null;
            }

            return compact('date', 'unknown', 'uncertain');
        };

        if (null === ($startDate = $parseDate($start))) {
            $this->logger->logDebug(
                'Ead3',
                "Failed to parse startDate $start, record {$this->source}."
                . $this->getID(),
                true
            );
            $this->storeWarning('invalid start date');
            return null;
        }

        if (null === ($endDate = $parseDate($end, '9', '12', null, '23:59:59'))) {
            $this->logger->logDebug(
                'Ead3',
                "Failed to parse endDate $end, record {$this->source}."
                . $this->getID(),
                true
            );
            $this->storeWarning('invalid end date');
            return null;
        }

        $startDateUnknown = $startDate['unknown'];
        $endDateUnknown = $endDate['unknown'];
        $endDateUncertain = $endDate['uncertain'];

        $startDate = $startDate['date'];
        $endDate = $endDate['date'];

        if (strtotime($startDate) > strtotime($endDate)) {
            $this->logger->logDebug(
                'Ead3',
                "Invalid date range {$startDate} - {$endDate}, record " .
                "{$this->source}." . $this->getID(),
                true
            );
            $this->storeWarning('invalid date range');
            $endDate = substr($startDate, 0, 4) . '-12-31T23:59:59Z';
        }

        return [
            'date' => [$startDate, $endDate],
            'startDateUnknown' => $startDateUnknown,
            'endDateUnknown' => $endDateUnknown,
            'endDateUncertain' => $endDateUncertain
        ];
    }

    /**
     * Return author name with role.
     *
     * @param string $name Name
     * @param string $role Role
     *
     * @return string
     */
    protected function getNameWithRole($name, $role = null)
    {
        return $role
            ? "$name " . strtolower($role)
            : $name;
    }

    /**
     * Helper function for getting controlaccess access elements filtered
     * by relator-attribute.
     *
     * @param string $nodeName    Name of node that contains the topic terms
     * @param array  $relators    Accepted relator-attribute values when relator
     *                            is defined.
     * @param bool   $identifiers Return identifiers instead of labels?
     *
     * @return array
     */
    protected function getTopicTermsFromNodeWithRelators(
        $nodeName,
        $relators,
        $identifiers = false
    ) {
        $result = [];
        if (!isset($this->doc->controlaccess->{$nodeName})) {
            return $result;
        }

        foreach ($this->doc->controlaccess->{$nodeName} as $node) {
            $relator = mb_strtolower(
                trim((string)($node['relator'] ?? '')),
                'UTF-8'
            );
            if (!$relator || in_array($relator, $relators)) {
                if ($identifiers) {
                    if ($id = $node['identifier']) {
                        $result[] = (string)$id;
                    }
                } elseif ($value = trim((string)$node->part)) {
                    $result[] = $value;
                }
            }
        }
        return $result;
    }

    /**
     * Get topics
     *
     * @return array
     */
    protected function getTopics()
    {
        return $this->getTopicTermsFromNodeWithRelators(
            'subject',
            self::SUBJECT_RELATORS
        );
    }

    /**
     * Get topic identifiers.
     *
     * @return array
     */
    protected function getTopicIDs(): array
    {
        $result = $this->getRawTopicIds();
        return $this->addNamespaceToAuthorityIds($result, 'geographic');
    }

    /**
     * Get all topic identifiers (for enrichment)
     *
     * @return array
     */
    public function getRawTopicIds(): array
    {
        return $this->getTopicTermsFromNodeWithRelators(
            'subject',
            self::SUBJECT_RELATORS,
            true
        );
    }

    /**
     * Get all geographic topic identifiers (for enrichment)
     *
     * @return array
     */
    public function getRawGeographicTopicIds(): array
    {
        return $this->getTopicTermsFromNodeWithRelators(
            'geogname',
            self::GEOGRAPHIC_SUBJECT_RELATORS,
            true
        );
    }

    /**
     * Get geographic topics
     *
     * @return array
     */
    protected function getGeographicTopics()
    {
        return $this->getTopicTermsFromNodeWithRelators(
            'geogname',
            self::GEOGRAPHIC_SUBJECT_RELATORS
        );
    }

    /**
     * Get geographic topics IDs
     *
     * @return array
     */
    protected function getGeographicTopicIDs()
    {
        $result = $this->getRawGeographicTopicIds();
        return $this->addNamespaceToAuthorityIds($result, 'geographic');
    }

    /**
     * Return format from predefined values
     *
     * @return string
     */
    public function getFormat()
    {
        $level1 = $level2 = null;

        $docLevel = (string)$this->doc->attributes()->level;
        $level1 = $docLevel === 'fonds' ? 'Document' : null;

        if (!isset($this->doc->controlaccess->genreform)) {
            return $docLevel;
        }

        $defaultFormat = null;
        foreach ($this->doc->controlaccess->genreform as $genreform) {
            $nonLangFormat = null;
            $format = null;
            foreach ($genreform->part as $part) {
                if (null === $nonLangFormat) {
                    $nonLangFormat = (string)$part;
                }
                $attributes = $part->attributes();
                if ((string)($attributes->lang ?? '') === 'fin') {
                    $format = (string)$part;
                    break;
                }
            }
            if (null === $format) {
                $format = $nonLangFormat;
            }
            if (null === $defaultFormat) {
                $defaultFormat = $format;
            }

            if (!$format) {
                continue;
            }

            $attr = $genreform->attributes();
            if (isset($attr->encodinganalog)) {
                $type = (string)$attr->encodinganalog;
                if ($type === 'ahaa:AI08') {
                    if ($level1 === null) {
                        $level1 = $format;
                    } else {
                        $level2 = $format;
                    }
                } elseif ($type === 'ahaa:AI57') {
                    $level2 = $format;
                }
            }
        }

        if (null === $level1) {
            $level1 = $defaultFormat ?? '';
        }

        return $level2 ? "$level1/$level2" : $level1;
    }

    /**
     * Get institution
     *
     * @return string
     */
    protected function getInstitution()
    {
        if (isset($this->doc->did->repository)) {
            foreach ($this->doc->did->repository as $repo) {
                $attr = $repo->attributes();
                if (! isset($attr->encodinganalog)
                    || 'ahaa:AI42' !== (string)$attr->encodinganalog
                ) {
                    continue;
                }
                foreach ($repo->corpname as $node) {
                    $attr = $node->attributes();
                    if (! isset($attr->identifier)) {
                        continue;
                    }
                    return (string)$attr->identifier;
                }
            }
        }
        return '';
    }

    /**
     *  Get description
     *
     * @return string
     */
    protected function getDescription()
    {
        if (!empty($this->doc->scopecontent)) {
            $desc = [];
            foreach ($this->doc->scopecontent as $el) {
                foreach ($el->p as $p) {
                    $desc[] = str_replace(
                        ["\r\n", "\n\r", "\r", "\n"],
                        '   /   ',
                        trim(html_entity_decode((string)$p))
                    );
                }
            }
            if (!empty($desc)) {
                return implode('   /   ', $desc);
            }
        }
        return '';
    }

    /**
     * Check whether the given node is a time interval element
     * and should not be included when collecting name elements.
     *
     * @param \SimpleXMLElement $node Node
     *
     * @return bool
     */
    protected function isTimeIntervalNode(\SimpleXMLElement $node) : bool
    {
        return mb_strtolower((string)$node->attributes()->localtype, 'UTF-8')
            === self::RELATOR_TIME_INTERVAL;
    }
}
