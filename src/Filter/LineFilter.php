<?php

/*
 * This file is part of the Behat Gherkin Parser.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Gherkin\Filter;

use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\RuleNode;
use Behat\Gherkin\Node\ScenarioInterface;

/**
 * Filters scenarios by definition line number.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
class LineFilter implements FilterInterface
{
    /**
     * @var int
     */
    protected $filterLine;

    /**
     * Initializes filter.
     *
     * @param int|numeric-string $filterLine Line of the scenario to filter on
     */
    public function __construct(int|string $filterLine)
    {
        $this->filterLine = (int) $filterLine;
    }

    /**
     * Checks if Feature matches specified filter.
     *
     * @param FeatureNode $feature Feature instance
     *
     * @return bool
     */
    public function isFeatureMatch(FeatureNode $feature)
    {
        return $this->filterLine === $feature->getLine();
    }

    /**
     * Checks if scenario or outline matches specified filter.
     *
     * @param ScenarioInterface $scenario Scenario or Outline node instance
     *
     * @return bool
     */
    public function isScenarioMatch(ScenarioInterface $scenario)
    {
        if ($this->filterLine === $scenario->getLine()) {
            return true;
        }

        $parentRule = RuleNode::resolveParentRule($scenario);
        if ($this->filterLine === $parentRule?->getLine()) {
            return true;
        }

        if ($scenario instanceof OutlineNode && $scenario->hasExamples()) {
            foreach ($scenario->getExampleTables() as $table) {
                if (in_array($this->filterLine, $table->getLines())) {
                    return true;
                }
            }
        }

        return false;
    }

    public function filterFeature(FeatureNode $feature)
    {
        $originalChildren = [];
        $filteredChildren = [];

        foreach ($feature->getExecutableChildren() as $scenarioOrRule) {
            $originalChildren[] = $scenarioOrRule;

            $filteredChild = match (true) {
                $scenarioOrRule instanceof ScenarioInterface => $this->filterScenario($feature, null, $scenarioOrRule),
                $scenarioOrRule instanceof RuleNode => $this->filterRule($feature, $scenarioOrRule),
                default => throw new \LogicException('Unexpected child type ' . $scenarioOrRule::class),
            };

            if ($filteredChild !== false) {
                $filteredChildren[] = $filteredChild;
            }
        }

        return $originalChildren === $filteredChildren ? $feature : $feature->withScenarios($filteredChildren);
    }

    private function filterScenario(FeatureNode $feature, ?RuleNode $rule, ScenarioInterface $scenario): ScenarioInterface|false
    {
        if (!$this->isScenarioMatch($scenario)) {
            return false;
        }

        if ($scenario instanceof OutlineNode && $scenario->hasExamples()) {
            foreach ($scenario->getExampleTables() as $exampleTable) {
                $table = $exampleTable->getTable();
                $lines = array_keys($table);

                if (in_array($this->filterLine, $lines)) {
                    $filteredTable = [$lines[0] => $table[$lines[0]]];

                    if ($lines[0] !== $this->filterLine) {
                        $filteredTable[$this->filterLine] = $table[$this->filterLine];
                    }

                    return $scenario->withTables([$exampleTable->withTable($filteredTable)]);
                }
            }
        }

        return $scenario;
    }

    private function filterRule(FeatureNode $feature, RuleNode $rule): RuleNode|false
    {
        $filteredChildren = array_values(array_filter(array_map(
            fn (ScenarioInterface $scenario) => $this->filterScenario($feature, $rule, $scenario),
            $rule->getExecutableChildren(),
        )));

        if ($filteredChildren === []) {
            // Drop the rule, no scenarios match
            return false;
        }

        // @todo do we want a `->withScenarios` or `->withExecutableChildren` rather than always merging background like this?
        if ($rule->hasBackground()) {
            array_unshift($filteredChildren, $rule->getBackground());
        }

        return $rule->withChildren($filteredChildren);
    }
}
