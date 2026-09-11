<?php

/*
 * This file is part of the Behat Gherkin Parser.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Behat\Gherkin\Filter;

use Behat\Gherkin\Filter\LineFilter;
use Behat\Gherkin\Node\FeatureNode;
use PHPUnit\Framework\Attributes\DataProvider;

class LineFilterTest extends FilterTestCase
{
    public function testIsFeatureMatchFilter(): void
    {
        $feature = new FeatureNode(null, null, [], null, [], '', '', null, 1);

        $filter = new LineFilter(1);
        $this->assertTrue($filter->isFeatureMatch($feature));

        $filter = new LineFilter(2);
        $this->assertFalse($filter->isFeatureMatch($feature));

        $filter = new LineFilter(3);
        $this->assertFalse($filter->isFeatureMatch($feature));
    }

    /**
     * @phpstan-return iterable<string,array{FeatureFilterTestFixture, int}>
     */
    public static function providerFilterFeature(): iterable
    {
        yield from self::providerFilterFeatureScenarios();
        yield from self::providerFilterFeatureOutlineExamples();
    }

    /**
     * @phpstan-return iterable<string,array{FeatureFilterTestFixture, int}>
     */
    private static function providerFilterFeatureScenarios(): iterable
    {
        yield 'simple feature, exact scenario line number' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Two scenarios                 
                2 :   Scenario: Scenario#1                 
                3 :     Given initial step                 
                4 :     When action occurs                 
                5 :     Then outcomes should be visible    
                6 :                                        
                7 : #   Scenario: Scenario#2                 
                8 : #     Given initial step                 
                9 : #     And another initial step           
                10: #     When action occurs                 
                11: #    Then outcomes should be visible     
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => true,
                    'Scenario#2' => false,
                ],
                stripLineNumbers: true,
            ),
            2,
        ];

        yield 'simple feature, other matching line number' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Two scenarios                 
                2 : #  Scenario: Scenario#1                 
                3 : #    Given initial step                 
                4 : #    When action occurs                 
                5 : #    Then outcomes should be visible    
                6 :                                        
                7 :    Scenario: Scenario#2                 
                8 :     Given initial step                 
                9 :     And another initial step           
                10:     When action occurs                 
                11:     Then outcomes should be visible     
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => false,
                    'Scenario#2' => true,
                ],
                stripLineNumbers: true,
            ),
            7,
        ];

        yield 'simple feature, within a scenario (matches nothing)' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Two scenarios                 
                2 : #  Scenario: Scenario#1                 
                3 : #    Given initial step                 
                4 : #    When action occurs                 
                5 : #    Then outcomes should be visible    
                6 : #                                       
                7 : #   Scenario: Scenario#2                 
                8 : #    Given initial step                 
                9 : #    And another initial step           
                10: #    When action occurs                 
                11: #    Then outcomes should be visible     
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => false,
                    'Scenario#2' => false,
                ],
                stripLineNumbers: true,
            ),
            5,
        ];
    }

    /**
     * @phpstan-return iterable<string,array{FeatureFilterTestFixture, int}>
     */
    private static function providerFilterFeatureOutlineExamples(): iterable
    {
        yield 'feature with outline, matches line of the Outline' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Long feature with outline
                2 : #  Scenario: Scenario#1
                3 : #   Given initial step
                4 : #   When action occurs
                5 : #   Then outcomes should be visible
                6 : #
                7 : # Scenario: Scenario#2
                8 : #   Given initial step
                9 : #   And another initial step
                10: #   When action occurs
                11: #   Then outcomes should be visible
                12:
                13:   Scenario Outline: Scenario#3
                14:     When <action> occurs
                15:     Then <outcome> should be visible
                16:
                17:    @etag1
                18:    Examples:
                19:      | action | outcome |
                20:      | act#1  | out#1   |
                21:      | act#2  | out#2   |
                22:
                23:    @etag2
                24:    Examples:
                25:      | action | outcome |
                26:      | act#3  | out#3   |
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => false,
                    'Scenario#2' => false,
                    'Scenario#3' => true,
                ],
                stripLineNumbers: true,
            ),
            13,
        ];

        yield 'feature with outline, matches one line in Example table' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Long feature with outline
                2 : #  Scenario: Scenario#1
                3 : #   Given initial step
                4 : #   When action occurs
                5 : #   Then outcomes should be visible
                6 : #
                7 : # Scenario: Scenario#2
                8 : #   Given initial step
                9 : #   And another initial step
                10: #   When action occurs
                11: #   Then outcomes should be visible
                12:
                13:   Scenario Outline: Scenario#3
                14:     When <action> occurs
                15:     Then <outcome> should be visible
                16:
                17:    @etag1
                18:    Examples:
                19:      | action | outcome |
                20:      | act#1  | out#1   |
                21: #     | act#2  | out#2   |
                22:
                23: #   @etag2
                24: #   Examples:
                25: #     | action | outcome |
                26: #     | act#3  | out#3   |
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => false,
                    'Scenario#2' => false,
                    'Scenario#3' => true,
                ],
                stripLineNumbers: true,
            ),
            20,
        ];

        yield 'feature with outline, matches different line in Example table' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Long feature with outline
                2 : #  Scenario: Scenario#1
                3 : #   Given initial step
                4 : #   When action occurs
                5 : #   Then outcomes should be visible
                6 : #
                7 : # Scenario: Scenario#2
                8 : #   Given initial step
                9 : #   And another initial step
                10: #   When action occurs
                11: #   Then outcomes should be visible
                12:
                13:   Scenario Outline: Scenario#3
                14:     When <action> occurs
                15:     Then <outcome> should be visible
                16:
                17: #   @etag1
                18: #   Examples:
                19: #     | action | outcome |
                20: #     | act#1  | out#1   |
                21: #     | act#2  | out#2   |
                22:
                23:    @etag2
                24:    Examples:
                25:      | action | outcome |
                26:      | act#3  | out#3   |
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => false,
                    'Scenario#2' => false,
                    'Scenario#3' => true,
                ],
                stripLineNumbers: true,
            ),
            26,
        ];

        yield 'feature with outline, matches one Example table header (parses as empty table, matches Scenario)' => [
            FeatureFilterTestFixture::fromCommentedExpectation(
                <<<'GHERKIN'
                1 : Feature: Long feature with outline
                2 : #  Scenario: Scenario#1
                3 : #   Given initial step
                4 : #   When action occurs
                5 : #   Then outcomes should be visible
                6 : #
                7 : # Scenario: Scenario#2
                8 : #   Given initial step
                9 : #   And another initial step
                10: #   When action occurs
                11: #   Then outcomes should be visible
                12:
                13:   Scenario Outline: Scenario#3
                14:     When <action> occurs
                15:     Then <outcome> should be visible
                16:
                17:    @etag1
                18:    Examples:
                19:      | action | outcome |
                20: #     | act#1  | out#1   |
                21: #     | act#2  | out#2   |
                22: #
                23: #   @etag2
                24: #   Examples:
                25: #     | action | outcome |
                26: #     | act#3  | out#3   |
                GHERKIN,
                expectScenarioMatches: [
                    'Scenario#1' => false,
                    'Scenario#2' => false,
                    'Scenario#3' => true,
                ],
                stripLineNumbers: true,
            ),
            19,
        ];
    }

    #[DataProvider('providerFilterFeature')]
    public function testFilterFeature(FeatureFilterTestFixture $testcase, int $filterLine): void
    {
        $this->assertFiltersFeatureAsExpected($testcase, new LineFilter($filterLine));
    }
}
