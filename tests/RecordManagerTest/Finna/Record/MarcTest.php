<?php

/**
 * Finna MARC Record Driver Test Class
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020-2023.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */

namespace RecordManagerTest\Finna\Record;

use RecordManager\Finna\Record\Marc;

/**
 * Finna MARC Record Driver Test Class
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */
class MarcTest extends \RecordManagerTest\Base\Record\RecordTestBase
{
    /**
     * Test MARC Record handling
     *
     * @return void
     */
    public function testMarc1()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc1.xml',
            [
                '__unit_test_no_source__' => [
                    'authority' => [
                        '*' => 'testauth',
                    ],
                ],
            ],
            'Base',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [
                '150',
                '150',
            ],
            'lccn' => '',
            'ctrlnum' => [
                'FCC005246184',
                '378890',
                '401416',
            ],
            'allfields' => [
                '978-951-31-4836-2',
                '9789513148362',
                '2345',
                'FOO',
                'doi1',
                'doi',
                'Hirsjärvi, Sirkka',
                'Tutki ja kirjoita',
                'Sirkka Hirsjärvi, Pirkko Remes, Paula Sajavaara',
                '17. uud. p.',
                'Helsinki',
                'Tammi',
                '2345 [2013?]',
                '18. p. 2013',
                'Summary field',
                'oppaat',
                'ft: kirjoittaminen',
                'apurahat',
                'tutkimusrahoitus',
                'tutkimuspolitiikka',
                'opinnäytteet',
                'tiedonhaku',
                'kielioppaat',
                'tutkimustyö',
                'tutkimus',
                'Remes, Pirkko',
                'Sajavaara, Paula',
            ],
            'language' => [
                'fin',
                'fin',
            ],
            'format' => 'Book',
            'author' => [
                'Hirsjärvi, Sirkka',
            ],
            'author_role' => [
                '',
            ],
            'author_sort' => 'Hirsjärvi, Sirkka',
            'author_variant' => [
                's h sh',
            ],
            'author2' => [
                'Remes, Pirkko',
                'Sajavaara, Paula',
            ],
            'author2_role' => [
                '',
                '',
            ],
            'author_corporate' => [],
            'author_corporate_role' => [],
            'author2_id_str_mv' => [
                'testauth.(TEST)1',
                'testauth.(TEST)2',
                'testauth.(TEST)3',
            ],
            'author2_id_role_str_mv' => [],
            'author2_variant' => [
                'p r pr',
                'p s ps',
            ],
            'author_additional' => [],
            'title' => 'Tutki ja kirjoita',
            'title_sub' => '',
            'title_short' => 'Tutki ja kirjoita',
            'title_full' => 'Tutki ja kirjoita / Sirkka Hirsjärvi, Pirkko Remes,'
                . ' Paula Sajavaara',
            'title_alt' => [],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => 'tutki ja kirjoita sirkka hirsjärvi pirkko remes'
                . ' paula sajavaara',
            'series' => [],
            'publisher' => [
                'Tammi',
            ],
            'publishDateSort' => '2013',
            'publishDate' => [
                '2013',
            ],
            'physical' => [],
            'dateSpan' => [],
            'edition' => '17. uud. p.',
            'contents' => [],
            'isbn' => [
                '9789513148362',
            ],
            'issn' => [],
            'doi_str_mv' => [
                'doi1',
                'doi2',
                'doi:3',
                'doi4',
            ],
            'callnumber-first' => '38.04',
            'callnumber-raw' => [
                '38.04',
                '38.03',
                'QC861.2 .B36',
            ],
            'callnumber-sort' => '38.04',
            'topic' => [
                'oppaat',
                'ft: kirjoittaminen',
                'apurahat',
                'tutkimusrahoitus',
                'tutkimuspolitiikka',
                'opinnäytteet',
                'tiedonhaku',
                'kielioppaat',
                'tutkimustyö',
                'tutkimus',
            ],
            'genre' => [],
            'geographic' => [],
            'geographic_id_str_mv' => [],
            'era' => [],
            'topic_facet' => [
                'oppaat',
                'ft: kirjoittaminen',
                'apurahat',
                'tutkimusrahoitus',
                'tutkimuspolitiikka',
                'opinnäytteet',
                'tiedonhaku',
                'kielioppaat',
                'tutkimustyö',
                'tutkimus',
            ],
            'genre_facet' => [],
            'geographic_facet' => [],
            'era_facet' => [],
            'url' => [
                'urn:doi:doi2',
                'urn:doif:not-doi',
                'http://doi.org/doi%3a3',
                'https://dx.doi.org/doi4',
            ],
            'illustrated' => 'Not Illustrated',
            'main_date_str' => '2013',
            'main_date' => '2013-01-01T00:00:00Z',
            'publication_daterange' => '[2013-01-01 TO 2013-12-31]',
            'search_daterange_mv' => [
                '[2013-01-01 TO 2013-12-31]',
            ],
            'publication_place_txt_mv' => [
                'Helsinki',
            ],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [
                'fin',
            ],
            'classification_txt_mv' => [
                'dlc qc861.2.b36',
                'dlc QC861.2 .B36',
                'ekl 38.04',
                'ekl 38.04',
                'ekl 38.03',
                'ekl 38.03',
            ],
            'classification_str_mv' => [
                'dlc qc861.2.b36',
                'dlc QC861.2 .B36',
                'ekl 38.04',
                'ekl 38.04',
                'ekl 38.03',
                'ekl 38.03',
            ],
            'callnumber-subject' => 'QC',
            'callnumber-label' => 'QC861',
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [
                'E 150 38 Hir 18. p. 2013 __unit_test_no_source__',
                'E 150 38 Hir 18. p. 2013 __unit_test_no_source__',
            ],
            'author_facet' => [
                'Hirsjärvi, Sirkka',
                'Remes, Pirkko',
                'Sajavaara, Paula',
            ],
            'format_ext_str_mv' => 'Book',
            'topic_id_str_mv' => [],
            'description' => 'Summary field',
            'media_type_str_mv' => [],
            'major_genre_str_mv' => 'nonfiction',
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');

        $keys = $record->getWorkIdentificationData();

        $expected = [
            [
                'authors' => [
                    [
                        'type' => 'author',
                        'value' => 'Hirsjärvi, Sirkka.',
                    ],
                    [
                        'type' => 'author',
                        'value' => 'Remes, Pirkko.',
                    ],
                    [
                        'type' => 'author',
                        'value' => 'Sajavaara, Paula.',
                    ],
                ],
                'authorsAltScript' => [],
                'titles' => [
                    [
                        'type' => 'title',
                        'value' => 'Tutki ja kirjoita /',
                    ],
                ],
                'titlesAltScript' => [],
            ],
        ];

        $this->compareArray($expected, $keys, 'getWorkIdentificationData');

        $this->assertEquals(
            ['(FOO)2345', '(FI-MELINDA)005246184'],
            $record->getUniqueIDs()
        );
    }

    /**
     * Test MARC Record handling
     *
     * @return void
     */
    public function testMarc2()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc2.xml',
            [],
            'Base',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [
                '123',
                '234',
            ],
            'lccn' => '',
            'ctrlnum' => [
                '1558192',
                'FCC002608043',
            ],
            'allfields' => [
                '0-534-51409-X',
                '9780534514099',
                '0-534-51400-6',
                '9780534514006',
                'Kalat, James W.',
                'Biological psychology',
                'James W. Kalat',
                '7th ed',
                'Belmont, CA',
                'Wadsworth',
                'cop. 2001.',
                'Liitteenä CD-ROM',
                '&12een',
                '&käytt&tdk',
                '&vanha&painos',
                'neuropsykologia',
                'biopsykologia',
                'neuropsykologi',
                'biopsykologi',
            ],
            'language' => [
                'eng',
                'eng',
            ],
            'format' => 'Book',
            'author' => [
                'Kalat, James W.',
            ],
            'author_role' => [
                '',
            ],
            'author_sort' => 'Kalat, James W.',
            'author_variant' => [
                'j w k jw jwk',
            ],
            'author2' => [],
            'author2_role' => [],
            'author_corporate' => [],
            'author_corporate_role' => [],
            'author2_id_str_mv' => [],
            'author2_id_role_str_mv' => [],
            'author_additional' => [],
            'title' => 'Biological psychology',
            'title_sub' => '',
            'title_short' => 'Biological psychology',
            'title_full' => 'Biological psychology / James W. Kalat',
            'title_alt' => [],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => 'biological psychology james w kalat',
            'series' => [],
            'publisher' => [
                'Wadsworth',
            ],
            'publishDateSort' => '2001',
            'publishDate' => [
                '2001',
            ],
            'physical' => [
                'xxiii, 551 sivua : kuvitettu + CD-ROM -levy',
            ],
            'dateSpan' => [],
            'edition' => '7th ed',
            'contents' => [],
            'isbn' => [
                '9780534514099',
                '9780534514006',
            ],
            'issn' => [],
            'doi_str_mv' => [],
            'callnumber-first' => '',
            'callnumber-raw' => [],
            'topic' => [
                'neuropsykologia',
                'biopsykologia',
                'neuropsykologi',
                'biopsykologi',
            ],
            'genre' => [],
            'geographic' => [],
            'geographic_id_str_mv' => [],
            'era' => [],
            'topic_facet' => [
                'neuropsykologia',
                'biopsykologia',
                'neuropsykologi',
                'biopsykologi',
            ],
            'genre_facet' => [],
            'geographic_facet' => [],
            'era_facet' => [],
            'url' => [],
            'illustrated' => 'Illustrated',
            'main_date_str' => '2001',
            'main_date' => '2001-01-01T00:00:00Z',
            'publication_daterange' => '[2001-01-01 TO 2001-12-31]',
            'search_daterange_mv' => [
                '[2001-01-01 TO 2001-12-31]',
            ],
            'publication_place_txt_mv' => [
                'Belmont, CA',
            ],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [
                'eng',
            ],
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [
                'L 123 L 616.8 __unit_test_no_source__',
                'Ll 234 Ll Course __unit_test_no_source__',
            ],
            'callnumber-sort' => '',
            'author_facet' => [
                'Kalat, James W.',
            ],
            'format_ext_str_mv' => 'Book',
            'topic_id_str_mv' => [
                'http://www.yso.fi/onto/yso/p14664',
                '(test)test\\.12',
                '(biotest)BIOTEST.12',
                '(biotest)(BIOTEST)1234',
            ],
            'description' => '',
            'media_type_str_mv' => [],
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');

        $this->assertEquals(['(FI-MELINDA)002608043'], $record->getUniqueIDs());
    }

    /**
     * Test MARC Record handling
     *
     * @return void
     */
    public function testMarcGeo()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc_geo.xml',
            [],
            'Base',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [
                '001',
            ],
            'lccn' => '',
            'ctrlnum' => [
                '(FI-Piki)Ppro837_107786',
                '(PIKI)Ppro837_107786',
                '(FI-MELINDA)000963219',
            ],
            'allfields' => [
                'Suomen tiekartta',
                'Vägkarta över Finland',
                '1.',
                'Suomen tiekartta 1',
                '1:200000',
                'Helsinki',
                'Maanmittaushallitus',
                '1946.',
                'Ahvenanmaa mittakaavassa 1:400000',
                'Kh-kokoelma',
                'tiekartat',
                'kartat',
                'Suomi',
                'Turun ja Porin lääni',
                'yso/fin',
                'Uudenmaan lääni',
                'Ahvenanmaa',
            ],
            'language' => [
                'fin',
                'fin',
                'swe',
            ],
            'format' => 'Map',
            'author' => [],
            'author_role' => [],
            'author2' => [],
            'author2_role' => [],
            'author_corporate' => [
                'Maanmittaushallitus',
            ],
            'author_corporate_role' => [
                '',
            ],
            'author2_id_str_mv' => [],
            'author2_id_role_str_mv' => [],
            'author_additional' => [],
            'title' => 'Suomen tiekartta = Vägkarta över Finland. 1.',
            'title_sub' => 'Vägkarta över Finland. 1.',
            'title_short' => 'Suomen tiekartta',
            'title_full' => 'Suomen tiekartta = Vägkarta över Finland. 1.',
            'title_alt' => [
                'Vägkarta över Finland',
                'Suomen tiekartta 1',
            ],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => 'suomen tiekartta vägkarta över finland 1',
            'series' => [],
            'publisher' => [
                '[Maanmittaushallitus]',
            ],
            'publishDateSort' => '1946',
            'publishDate' => [
                '1946',
            ],
            'physical' => [
                '1 kartta : värillinen ; taitettuna 26 x 13 cm',
            ],
            'dateSpan' => [],
            'edition' => '',
            'contents' => [],
            'issn' => [],
            'doi_str_mv' => [],
            'callnumber-first' => '42.02',
            'callnumber-raw' => [
                '42.02',
            ],
            'callnumber-sort' => '42.02',
            'topic' => [
                'tiekartat',
                'kartat Suomi',
            ],
            'genre' => [],
            'geographic' => [
                'Turun ja Porin lääni',
                'Uudenmaan lääni',
                'Ahvenanmaa',
            ],
            'geographic_id_str_mv' => [
                'http://www.yso.fi/onto/yso/p94448',
                'http://www.yso.fi/onto/yso/p94460',
                'http://www.yso.fi/onto/yso/p94081',
            ],
            'era' => [],
            'topic_facet' => [
                'tiekartat',
                'kartat',
            ],
            'genre_facet' => [],
            'geographic_facet' => [
                'Suomi',
                'Turun ja Porin lääni',
                'Uudenmaan lääni',
                'Ahvenanmaa',
            ],
            'era_facet' => [],
            'url' => [],
            'illustrated' => 'Not Illustrated',
            'main_date_str' => '1946',
            'main_date' => '1946-01-01T00:00:00Z',
            'publication_daterange' => '[1946-01-01 TO 1946-12-31]',
            'search_daterange_mv' => [
                '[1946-01-01 TO 1946-12-31]',
            ],
            'publication_place_txt_mv' => [
                'Helsinki',
            ],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [
                'fin',
                'swe',
            ],
            'location_geo' => [
                'ENVELOPE(19.5, 24.75, 60.666666666667, 59.8)',
                'ENVELOPE(19.5, 24.75, 60.666666666667, 59.800277777778)',
            ],
            'center_coords' => '22.125 60.233333333333',
            'classification_txt_mv' => [
                'ykl 42.02',
                'ykl 42.02',
            ],
            'major_genre_str_mv' => 'nonfiction',
            'classification_str_mv' => [
                'ykl 42.02',
                'ykl 42.02',
            ],
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [
                '1 001 __unit_test_no_source__',
            ],
            'author_facet' => [
                'Maanmittaushallitus',
            ],
            'format_ext_str_mv' => 'Map',
            'topic_id_str_mv' => [],
            'description' => '',
            'media_type_str_mv' => [],
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');
    }

    /**
     * Test MARC Thesis Record handling
     *
     * @return void
     */
    public function testMarcThesis1()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-thesis1.xml',
            [],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [],
            'lccn' => '',
            'ctrlnum' => [],
            'allfields' => [
                'Author, Test',
                'Thesis Title',
                'Test Author',
                'Helsinki',
                'Kansalliskirjasto',
                '2020',
                'AMK-opinnäytetypo',
                'Sample Program',
                '2020.',
                'AMK-opinnäytetyö',
                'Second Sample Program',
                'testaus',
                'AMK-opinnäytetyö',
            ],
            'language' => [
                'fin',
            ],
            'format' => 'BachelorsThesisPolytechnic',
            'author' => [
                'Author, Test',
            ],
            'author_role' => [
                '',
            ],
            'author_sort' => 'Author, Test',
            'author_variant' => [
                't a ta',
            ],
            'author2' => [],
            'author2_role' => [],
            'author_corporate' => [],
            'author_corporate_role' => [],
            'author2_id_str_mv' => [],
            'author2_id_role_str_mv' => [],
            'author_additional' => [],
            'title' => 'Thesis Title',
            'title_sub' => '',
            'title_short' => 'Thesis Title',
            'title_full' => 'Thesis Title / Test Author',
            'title_alt' => [],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => 'thesis title test author',
            'series' => [],
            'publisher' => [
                'Kansalliskirjasto',
            ],
            'publishDateSort' => '2020',
            'publishDate' => [
                '2020',
            ],
            'physical' => [],
            'dateSpan' => [],
            'edition' => '',
            'contents' => [],
            'issn' => [],
            'doi_str_mv' => [],
            'callnumber-first' => '614.8',
            'callnumber-raw' => [
                '614.8',
            ],
            'callnumber-sort' => '614.8',
            'topic' => [
                'testaus',
            ],
            'genre' => [],
            'geographic' => [],
            'geographic_id_str_mv' => [],
            'era' => [],
            'topic_facet' => [
                'testaus',
            ],
            'genre_facet' => [],
            'geographic_facet' => [],
            'era_facet' => [],
            'url' => [],
            'illustrated' => 'Not Illustrated',
            'main_date_str' => '2020',
            'main_date' => '2020-01-01T00:00:00Z',
            'publication_daterange' => '[2020-01-01 TO 2020-12-31]',
            'search_daterange_mv' => [
                '[2020-01-01 TO 2020-12-31]',
            ],
            'publication_place_txt_mv' => [
                'Helsinki',
            ],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [],
            'classification_txt_mv' => [
                'udkx 614.8',
            ],
            'major_genre_str_mv' => 'nonfiction',
            'classification_str_mv' => [
                'udkx 614.8',
            ],
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [],
            'author_facet' => [
                'Author, Test',
            ],
            'format_ext_str_mv' => 'BachelorsThesisPolytechnic',
            'topic_id_str_mv' => [
                'http://www.yso.fi/onto/yso/p8471',
            ],
            'description' => '',
            'media_type_str_mv' => [],
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');
    }

    /**
     * Test MARC Thesis Record handling
     *
     * @return void
     */
    public function testMarcThesis2()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-thesis2.xml',
            [],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [],
            'lccn' => '',
            'ctrlnum' => [],
            'allfields' => [
                'Author, Test',
                'Thesis Title',
                'Test Author',
                'Helsinki',
                'Kansalliskirjasto',
                '2020',
                'testaus',
                'AMK-opinnäytetyö',
                'Sample Program',
                '2020.',
                'Second Sample Program',
            ],
            'language' => [
                'fin',
            ],
            'format' => 'BachelorsThesisPolytechnic',
            'author' => [
                'Author, Test',
            ],
            'author_role' => [
                '',
            ],
            'author_sort' => 'Author, Test',
            'author_variant' => [
                't a ta',
            ],
            'author2' => [],
            'author2_role' => [],
            'author_corporate' => [],
            'author_corporate_role' => [],
            'author2_id_str_mv' => [],
            'author2_id_role_str_mv' => [],
            'author_additional' => [],
            'title' => 'Thesis Title',
            'title_sub' => '',
            'title_short' => 'Thesis Title',
            'title_full' => 'Thesis Title / Test Author',
            'title_alt' => [],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => 'thesis title test author',
            'series' => [],
            'publisher' => [
                'Kansalliskirjasto',
            ],
            'publishDateSort' => '2020',
            'publishDate' => [
                '2020',
            ],
            'physical' => [],
            'dateSpan' => [],
            'edition' => '',
            'contents' => [],
            'issn' => [],
            'doi_str_mv' => [],
            'callnumber-first' => '614.8',
            'callnumber-raw' => [
                '614.8',
            ],
            'callnumber-sort' => '614.8',
            'topic' => [
                'testaus',
            ],
            'genre' => [],
            'geographic' => [],
            'geographic_id_str_mv' => [],
            'era' => [],
            'topic_facet' => [
                'testaus',
            ],
            'genre_facet' => [],
            'geographic_facet' => [],
            'era_facet' => [],
            'url' => [],
            'illustrated' => 'Not Illustrated',
            'main_date_str' => '2020',
            'main_date' => '2020-01-01T00:00:00Z',
            'publication_daterange' => '[2020-01-01 TO 2020-12-31]',
            'search_daterange_mv' => [
                '[2020-01-01 TO 2020-12-31]',
            ],
            'publication_place_txt_mv' => [
                'Helsinki',
            ],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [],
            'classification_txt_mv' => [
                'udkx 614.8',
            ],
            'major_genre_str_mv' => 'nonfiction',
            'classification_str_mv' => [
                'udkx 614.8',
            ],
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [],
            'author_facet' => [
                'Author, Test',
            ],
            'format_ext_str_mv' => 'BachelorsThesisPolytechnic',
            'topic_id_str_mv' => [
                'http://www.yso.fi/onto/yso/p8471',
            ],
            'description' => '',
            'media_type_str_mv' => [],
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');
    }

    /**
     * Test MARC topics
     *
     * @return void
     */
    public function testMarcTopic()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-topic.xml',
            [
                '__unit_test_no_source__' => [
                    'authority' => [
                        '*' => 'testauth',
                    ],
                    'authority_id_regex' => [
                        '*' => '/^\(FI-ASTERI-N\)*/',
                    ],
                ],
            ],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [],
            'lccn' => '',
            'ctrlnum' => [],
            'allfields' => [
                'Wiik, Maria',
                'Berlioz, Hector',
                'finaf',
            ],
            'language' => [],
            'format' => 'Book',
            'author' => [],
            'author_role' => [],
            'author_sort' => '',
            'author2' => [],
            'author2_role' => [],
            'author_corporate' => [],
            'author_corporate_role' => [],
            'author2_id_str_mv' => [],
            'author2_id_role_str_mv' => [],
            'author_additional' => [],
            'title' => '',
            'title_sub' => '',
            'title_short' => '',
            'title_full' => '',
            'title_alt' => [],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => '',
            'series' => [],
            'publisher' => [],
            'publishDateSort' => '2015',
            'publishDate' => [
                '2015',
            ],
            'physical' => [],
            'dateSpan' => [],
            'edition' => '',
            'contents' => [],
            'issn' => [],
            'doi_str_mv' => [],
            'callnumber-first' => '',
            'callnumber-raw' => [],
            'callnumber-sort' => '',
            'topic' => [
                'Wiik, Maria',
                'Berlioz, Hector',
            ],
            'genre' => [],
            'geographic' => [],
            'geographic_id_str_mv' => [],
            'era' => [],
            'topic_facet' => [
                'Wiik, Maria',
                'Berlioz, Hector',
            ],
            'genre_facet' => [],
            'geographic_facet' => [],
            'era_facet' => [],
            'url' => [],
            'illustrated' => 'Illustrated',
            'main_date_str' => '2015',
            'main_date' => '2015-01-01T00:00:00Z',
            'publication_daterange' => '[2015-01-01 TO 2015-12-31]',
            'search_daterange_mv' => [
                '[2015-01-01 TO 2015-12-31]',
            ],
            'publication_place_txt_mv' => [],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [],
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [],
            'author_facet' => [],
            'format_ext_str_mv' => 'Book',
            'topic_id_str_mv' => [
                'testauth.(FI-ASTERI-N)000107840',
                'testauth.(FI-ASTERI-N)000189739',
            ],
            'description' => '',
            'media_type_str_mv' => [],
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');
    }

    /**
     * Test MARC date range
     *
     * @return void
     */
    public function testMarcDateRange()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-daterange.xml',
            [],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'marc',
            'building' => [],
            'lccn' => '',
            'ctrlnum' => [],
            'allfields' => [
                'Lentolehti',
                'Pienviljelijäin rakennuskysymyksiä',
                'Helsinki',
                'Maatalousseurojen keskusliitto',
                '1940 [vuosien 1931 ja 1943 välillä?]',
            ],
            'language' => [
                'fin',
            ],
            'format' => 'Serial',
            'author' => [],
            'author_role' => [],
            'author_sort' => '',
            'author2' => [],
            'author2_role' => [],
            'author_corporate' => [],
            'author_corporate_role' => [],
            'author2_id_str_mv' => [],
            'author2_id_role_str_mv' => [],
            'author_additional' => [],
            'title' => 'Lentolehti',
            'title_sub' => '',
            'title_short' => 'Lentolehti',
            'title_full' => 'Lentolehti Pienviljelijäin rakennuskysymyksiä',
            'title_alt' => [],
            'title_old' => [],
            'title_new' => [],
            'title_sort' => 'lentolehti pienviljelijäin rakennuskysymyksiä',
            'series' => [],
            'publisher' => [
                '[Maatalousseurojen keskusliitto]',
            ],
            'publishDateSort' => '1931',
            'publishDate' => [
                '1931',
            ],
            'physical' => [],
            'dateSpan' => [],
            'edition' => '',
            'contents' => [],
            'issn' => [],
            'doi_str_mv' => [],
            'callnumber-first' => '',
            'callnumber-raw' => [],
            'callnumber-sort' => '',
            'topic' => [],
            'genre' => [],
            'geographic' => [],
            'geographic_id_str_mv' => [],
            'era' => [],
            'topic_facet' => [],
            'genre_facet' => [],
            'geographic_facet' => [],
            'era_facet' => [],
            'url' => [],
            'illustrated' => 'Not Illustrated',
            'main_date_str' => '1931',
            'main_date' => '1931-01-01T00:00:00Z',
            'publication_daterange' => '[1931-01-01 TO 1943-12-31]',
            'search_daterange_mv' => [
                '[1931-01-01 TO 1943-12-31]',
            ],
            'publication_place_txt_mv' => [
                'Helsinki',
            ],
            'subtitle_lng_str_mv' => [],
            'original_lng_str_mv' => [],
            'source_str_mv' => '__unit_test_no_source__',
            'datasource_str_mv' => [
                '__unit_test_no_source__',
            ],
            'other_issn_str_mv' => [],
            'other_issn_isn_mv' => [],
            'linking_issn_str_mv' => [],
            'holdings_txtP_mv' => [],
            'author_facet' => [],
            'format_ext_str_mv' => 'Serial',
            'topic_id_str_mv' => [],
            'description' => '',
            'media_type_str_mv' => [],
        ];

        $this->compareArray($expected, $fields, 'toSolrArray');
    }

    /**
     * Test MARC UDK and extra classifications
     *
     * @return void
     */
    public function testMarcUDKAndExtraClassifications()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-udk.xml',
            [
                '__unit_test_no_source__' => [
                    'driverParams' => [
                        'classifications = "245a=title"',
                    ],
                ],
            ],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();

        $this->assertEquals(
            [
                'udkf fennica080',
                'udkf finuc-s080',
                'udk2 new080-1',
                'udk2 new080-2',
                'udkx unknown080-1',
                'udkx unknown080-2',
                'title Lentolehti',
            ],
            $fields['classification_txt_mv']
        );
    }

    /**
     * Data provider for testMarcAudioBooks
     *
     * @return array
     */
    public static function marcAudioBooksProvider(): array
    {
        return [
            [
                'AudioBookDaisy',
                'marc-daisy1.xml',
            ],
            [
                'AudioBookDaisy',
                'marc-daisy2.xml',
            ],
            [
                'AudioBookDaisy',
                'marc-daisy3.xml',
            ],
            [
                'AudioBookDaisy',
                'marc-daisy4.xml',
            ],
            [
                'AudioBookDaisy',
                'marc-daisy5.xml',
            ],
            [
                'AudioBookOverDrive',
                'marc-overdrive1.xml',
            ],
        ];
    }

    /**
     * Test MARC audio book formats
     *
     * @dataProvider marcAudioBooksProvider
     *
     * @return void
     */
    public function testMarcAudioBooks()
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-udk.xml',
            [],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();

        $this->assertEquals(
            [
                'udkf fennica080',
                'udkf finuc-s080',
                'udk2 new080-1',
                'udk2 new080-2',
                'udkx unknown080-1',
                'udkx unknown080-2',
            ],
            $fields['classification_txt_mv']
        );
    }

    /**
     * Test MARC media types
     *
     * @return void
     */
    public function testMarcMediaTypes(): void
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc_media_types.xml',
            [],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        $this->assertEquals(
            [
                'audio/x-wav',
                'application/pdf',
            ],
            $fields['media_type_str_mv']
        );
    }

    /**
     * Test MARC performers
     *
     * @return void
     */
    public function testMarcPerformers(): void
    {
        $record = $this->createMarcRecord(
            Marc::class,
            'marc-performers.xml',
            [],
            'Finna',
            [
                $this->createMock(\RecordManager\Base\Record\PluginManager::class),
            ]
        );
        $fields = $record->toSolrArray();
        $this->assertEquals(
            [
                'viulu/viulu (1)',
                'sello/sello (2)',
                'piano/piano (3)',
                'sopraano/sopraano',
            ],
            $fields['performer_str_mv']
        );
        $this->assertEquals(
            7,
            $fields['performer_total_int']
        );
    }
}
