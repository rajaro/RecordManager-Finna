<?php
/**
 * Finna EAD3 Record Driver Test Class
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */
namespace RecordManagerTest\Finna\Record;

use RecordManager\Finna\Record\Ead3;

/**
 * Finna EAD3 Record Driver Test Class
 *
 * @category DataManagement
 * @package  RecordManager
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://github.com/NatLibFi/RecordManager
 */
class Ead3Test extends \RecordManagerTest\Base\Record\RecordTest
{
    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa()
    {
        // 1985-02-02/1995-12-01
        $fields = $this->createRecord(Ead3::class, 'ahaa.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1985-02-02 TO 1995-12-01]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa2()
    {
        // 1985-02/1995-12
        $fields = $this->createRecord(Ead3::class, 'ahaa2.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1985-02-01 TO 1995-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa3()
    {
        // 1985-02/1995-11
        $fields = $this->createRecord(Ead3::class, 'ahaa3.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1985-02-01 TO 1995-11-30]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa4()
    {
        // 1985/1995
        $fields = $this->createRecord(Ead3::class, 'ahaa4.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1985-01-01 TO 1995-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa5()
    {
        // uuuu-uu-10/1995-05-uu
        $fields = $this->createRecord(Ead3::class, 'ahaa5.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[0000-01-10 TO 1995-05-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa6()
    {
        // unknown/open
        $fields = $this->createRecord(Ead3::class, 'ahaa6.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[0000-01-01 TO 9999-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa8()
    {
        // uuuu-12-uu/unknown
        $fields = $this->createRecord(Ead3::class, 'ahaa8.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[0000-12-01 TO 9999-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa9()
    {
        // 1900/1940-03-02
        $fields = $this->createRecord(Ead3::class, 'ahaa9.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1900-01-01 TO 1940-03-02]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa10()
    {
        // 195u/1960-01-01
        $fields = $this->createRecord(Ead3::class, 'ahaa10.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1950-01-01 TO 1960-01-01]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa11()
    {
        // uu5u-11-05/u960-01-01
        $fields = $this->createRecord(Ead3::class, 'ahaa11.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[0050-11-05 TO 9960-01-01]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa12()
    {
        // Prefer unitdate with label "Ajallinen kattavuus"
        $fields = $this->createRecord(Ead3::class, 'ahaa12.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1970-01-01 TO 1971-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test AHAA EAD3 record handling
     *
     * @return void
     */
    public function testAhaa13()
    {
        // Discard unitdate with label "Ajallinen kattavuus" when starttime or
        // endtime is unknown
        $fields = $this->createRecord(Ead3::class, 'ahaa13.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1992-01-01 TO 1993-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Helper function for getTestTitleYearRange
     *
     * @param string $newTitle new title for test case
     *
     * @return void
     */
    public function modifyAhaa14Fixture($newTitle)
    {
        $fixture = $this->getFixture('record/ahaa14.xml', 'Finna');
        $fixturePath = $this->getFixturePath('record/ahaa14.xml', 'Finna');
        $titleReg = '/>(.*?)<\/unittitle>/';
        $title = ">" . $newTitle . "</unittitle>";
        $fixture = preg_replace(
            $titleReg,
            $title,
            $fixture
        );
        file_put_contents($fixturePath, $fixture);
    }

    /**
     * Data provider for testTitleYearRange
     *
     * @return Generator
     */
    public function getTestTitleYearRange()
    {
        $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
        $mdash = html_entity_decode('&#x2014;', ENT_NOQUOTES, 'UTF-8');
        $this->modifyAhaa14Fixture(
            "Opintokirja. Helsingin yliopisto (1932{$ndash}1935)"
        );
        $record = $this->createRecord(Ead3::class, 'ahaa14.xml', [], 'Finna')
            ->toSolrArray();
        yield 'test ndash' => [
            "Opintokirja. Helsingin yliopisto (1932{$ndash}1935)",
            $record['title']
        ];

        $this->modifyAhaa14Fixture(
            "Opintokirja. Helsingin yliopisto 1932{$mdash}1935"
        );
        $record = $this->createRecord(Ead3::class, 'ahaa14.xml', [], 'Finna')
            ->toSolrArray();
        yield 'test mdash' => [
            "Opintokirja. Helsingin yliopisto 1932{$mdash}1935",
            $record['title']
        ];

        $this->modifyAhaa14Fixture(
            "Opintokirja. Helsingin yliopisto (1932 - 1935)"
        );
        $record = $this->createRecord(Ead3::class, 'ahaa14.xml', [], 'Finna')
            ->toSolrArray();
        yield 'test dash' => [
            "Opintokirja. Helsingin yliopisto (1932 - 1935)",
            $record['title']
        ];

        $this->modifyAhaa14Fixture(
            "Opintokirja. Helsingin yliopisto 1932-1935"
        );
        $record = $this->createRecord(Ead3::class, 'ahaa14.xml', [], 'Finna')
            ->toSolrArray();
        yield 'test dash without whitespaces' => [
            "Opintokirja. Helsingin yliopisto 1932-1935",
            $record['title']
        ];

        $this->modifyAhaa14Fixture("Opintokirja. Helsingin yliopisto");
        $record = $this->createRecord(Ead3::class, 'ahaa14.xml', [], 'Finna')
            ->toSolrArray();
        yield 'test without year range' => [
            "Opintokirja. Helsingin yliopisto (1932{$ndash}1935)",
            $record['title']
        ];
    }

    /**
     * Test AHAA EAD3 title year range handling
     *
     * @param array $expected Expected results
     * @param array $input    Input
     *
     * @dataProvider getTestTitleYearRange
     *
     * @return void
     */
    public function testTitleYearRange($expected, $input)
    {
        $this->assertEquals($expected, $input);
    }

    /**
     * Test FSD EAD3 record handling
     *
     * @return void
     */
    public function testFsd()
    {
        // uu5u-11-05/u960-01-01
        $fields = $this->createRecord(Ead3::class, 'fsd.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[2014-02-17 TO 2014-03-14]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test YKSA EAD3 record handling
     *
     * @return void
     */
    public function testYksa()
    {
        // <unitdate>1918-1931</unitdate>
        $fields = $this->createRecord(Ead3::class, 'yksa.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1918-01-01 TO 1931-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test YKSA EAD3 record handling
     *
     * @return void
     */
    public function testYksa2()
    {
        // <unitdate>1931</unitdate>
        $fields = $this->createRecord(Ead3::class, 'yksa2.xml', [], 'Finna')
            ->toSolrArray();
        $this->assertContains(
            '[1931-01-01 TO 1931-12-31]',
            $fields['search_daterange_mv']
        );
    }

    /**
     * Test SKS EAD3 record handling
     *
     * @return void
     */
    public function testSKS()
    {
        $fields = $this->createRecord(Ead3::class, 'sks.xml', [], 'Finna')
            ->toSolrArray();
        unset($fields['fullrecord']);

        $expected = [
            'record_format' => 'ead3',
            'ctrlnum' => '',
            'allfields' => [
                'Gustaf Edvard Sundvallin kokoelma',
            ],
            'description' => 'G. E. Sundvallin tallentama murresatu Luvialta.',
            'author' => [
                'Sundvall, Gustaf Edvard',
                'Sundvall, Gustaf Edvard',
                'Sundvall, Gustaf Edvard',
                'Ingman, Anders Wilhelm',
                'Sundvall, Gustaf Edvard',
            ],
            'author_sort' => false,
            'author_corporate' => [],
            'geographic_facet' => [
                'Luvia',
                'Luvia',
                'Luvia',
            ],
            'geographic' => [
                'Luvia',
                'Luvia',
                'Luvia',
            ],
            'topic_facet' => [
                'folk tales',
                'kansansadut',
                'folksagor',
                'fairy tales',
                'sadut',
                'sagor',
                'folklore collectors',
                'perinteenkerääjät',
                'traditionsinsamlare',
            ],
            'topic' => [
                'folk tales',
                'kansansadut',
                'folksagor',
                'fairy tales',
                'sadut',
                'sagor',
                'folklore collectors',
                'perinteenkerääjät',
                'traditionsinsamlare',
            ],
            'format' => 'Teksti',
            'institution' => '102268433',
            'series' => 'Tekstit/Gustaf Edvard Sundvallin kokoelma',
            'title_sub' => '1',
            'title_short' => 'Sundvall Gustaf Edvard S 1:a) 1 (1881)',
            'title' => '1 Sundvall Gustaf Edvard S 1:a) 1 (1881)',
            'title_sort' => '1 sundvall gustaf edvard s 1:a) 1 (1881)',
            'title_full' => '1 Sundvall Gustaf Edvard S 1:a) 1 (1881)',
            'language' => [
                'fin',
            ],
            'physical' => [],
            'thumbnail' => '',
            'hierarchytype' => 'Default',
            'hierarchy_top_id' => '237990354',
            'hierarchy_top_title' => 'Gustaf Edvard Sundvallin kokoelma',
            'hierarchy_sequence' => '0000003',
            'hierarchy_parent_id' => '237990354_238008149',
            'hierarchy_parent_title' => 'Tekstit/Gustaf Edvard Sundvallin kokoelma',
            'unit_daterange' => '[1881-01-01 TO 1881-12-31]',
            'search_daterange_mv' => [
                '[1881-01-01 TO 1881-12-31]',
            ],
            'era_facet' => '1881',
            'main_date_str' => '1881',
            'main_date' => '1881-01-01T00:00:00Z',
            'hierarchy_sequence_str' => '0000003',
            'source_str_mv' => '102268433',
            'datasource_str_mv' => '__unit_test_no_source__',
            'online_boolean' => true,
            'online_str_mv' => '102268433',
            'free_online_boolean' => true,
            'free_online_str_mv' => '102268433',
            'identifier' => '238612737',
            'material' => [
                'Aineistotyyppi: Text 5 Styck',
                'Aineistotyyppi: Text 5 Pieces',
                'Aineistotyyppi: Teksti, järjestetty 5 Kappaletta',
            ],
            'usage_rights_str_mv' => [
                'restricted',
            ],
            'usage_rights_ext_str_mv' => [
                'restricted',
            ],
            'author_role' => [],
            'author_variant' => [
                'Sundwall, Gustaf Edvard',
                'Sundwall, Gustaf Edvard',
                'Sundwall, Gustaf Edvard',
                'Ingman, A.W.',
                'Sundwall, Gustaf Edvard',
            ],
            'author_facet' => [
                'Sundvall, Gustaf Edvard',
                'Sundvall, Gustaf Edvard',
                'Sundvall, Gustaf Edvard',
                'Ingman, Anders Wilhelm',
                'Sundvall, Gustaf Edvard',
            ],
            'author2_id_str_mv' => [
                'EAC_228204328',
                'EAC_228598319',
            ],
            'author2_id_role_str_mv' => [
                'EAC_228204328###Luovuttaja',
                'EAC_228204328###Luovuttaja',
                'EAC_228204328###Kirjoittaja',
                'EAC_228204328###Kirjoittaja',
                'EAC_228204328###Kerääjä',
                'EAC_228204328###Kerääjä',
                'EAC_228598319###Luovuttaja',
                'EAC_228598319###Luovuttaja',
                'EAC_228204328###Kokoelmanmuodostaja',
                'EAC_228204328###Kokoelmanmuodostaja',
            ],
            'format_ext_str_mv' => 'Teksti',
            'topic_id_str_mv' => [
                'http://www.yso.fi/onto/koko/p9995',
                'http://www.yso.fi/onto/koko/p9995',
                'http://www.yso.fi/onto/koko/p9995',
                'http://www.yso.fi/onto/koko/p16542',
                'http://www.yso.fi/onto/koko/p16542',
                'http://www.yso.fi/onto/koko/p16542',
                'http://www.yso.fi/onto/koko/p74073',
                'http://www.yso.fi/onto/koko/p74073',
                'http://www.yso.fi/onto/koko/p74073',
            ],
            'geographic_id_str_mv' => [
                'http://www.yso.fi/onto/yso/p105341',
                'http://www.yso.fi/onto/yso/p105341',
                'http://www.yso.fi/onto/yso/p105341',
            ],
        ];

        $this->assertEquals(
            $expected,
            $fields
        );
    }

    /**
     * Test date range parsing
     *
     * @return void
     */
    public function testParseDateRange()
    {
        $record = $this->createRecord(Ead3::class, 'yksa.xml', [], 'Finna');
        $reflection = new \ReflectionObject($record);
        $parseDateRange = $reflection->getMethod('parseDateRange');
        $parseDateRange->setAccessible(true);

        $this->assertEquals(
            [
                'date' => ['2021-01-01T00:00:00Z', '2021-12-31T23:59:59Z'],
                'startDateUnknown' => false,
                'endDateUnknown' => false,
            ],
            $parseDateRange->invokeArgs($record, ['2021/2021'])
        );
        $this->assertEquals(
            [
                'date' => ['2022-01-01T00:00:00Z', '2022-12-31T23:59:59Z'],
                'startDateUnknown' => false,
                'endDateUnknown' => false,
            ],
            $parseDateRange->invokeArgs($record, ['2022/2021'])
        );
        $this->assertEquals(
            null,
            $parseDateRange->invokeArgs($record, ['11999/2021'])
        );
        $this->assertEquals(
            null,
            $parseDateRange->invokeArgs($record, ['2010, 2020, 2021'])
        );
    }
}
