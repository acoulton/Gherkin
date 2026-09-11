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
use Behat\Gherkin\Node\RuleNode;
use Behat\Gherkin\Node\ScenarioInterface;

/**
 * Abstract filter class.
 *
 * @author Konstantin Kudryashov <ever.zet@gmail.com>
 */
abstract class ComplexFilter implements ComplexFilterInterface
{
    /**
     * Filters feature according to the filter.
     *
     * @return FeatureNode
     */
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

    protected function filterScenario(FeatureNode $feature, ?RuleNode $rule, ScenarioInterface $scenario): ScenarioInterface|false
    {
        return $this->isScenarioMatch($feature, $scenario) ? $scenario : false;
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
