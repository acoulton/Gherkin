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

            if ($scenarioOrRule instanceof ScenarioInterface) {
                if ($this->isScenarioMatch($feature, $scenarioOrRule)) {
                    $filteredChildren[] = $scenarioOrRule;
                }
            } else {
                $filteredRule = $this->filterRule($feature, $scenarioOrRule);
                if ($filteredRule !== false) {
                    $filteredChildren[] = $filteredRule;
                }
            }
        }

        return $originalChildren === $filteredChildren ? $feature : $feature->withScenarios($filteredChildren);
    }

    private function filterRule(FeatureNode $feature, RuleNode $rule): RuleNode|false
    {
        $filteredChildren = array_values(array_filter(
            $rule->getExecutableChildren(),
            fn (ScenarioInterface $scenario) => $this->isScenarioMatch($feature, $scenario)
        ));

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
