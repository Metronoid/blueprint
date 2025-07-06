<?php

namespace BlueprintExtensions\StateMachine\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Model;

class StateMachineLexer implements Lexer
{
    /**
     * Parse state machine configuration from the tokens and add to the tree.
     *
     * @param array $tokens The parsed YAML tokens
     * @return array The modified tree with state machine data
     */
    public function analyze(array $tokens): array
    {
        $tree = [];

        if (isset($tokens['models'])) {
            foreach ($tokens['models'] as $name => $definition) {
                if (isset($definition['state_machine'])) {
                    $stateMachineConfig = $this->parseStateMachineConfig($definition['state_machine']);
                    
                    if (!empty($stateMachineConfig)) {
                        $tree['state_machines'][$name] = $stateMachineConfig;
                    }
                    
                    // Remove state_machine from the model definition so Blueprint doesn't try to parse it as a column
                    unset($tokens['models'][$name]['state_machine']);
                }
            }
        }

        return $tree;
    }

    /**
     * Parse state machine configuration.
     *
     * @param array $config The state machine configuration
     * @return array The parsed state machine configuration
     */
    private function parseStateMachineConfig(array $config): array
    {
        $stateMachine = [];

        // Parse basic configuration
        $stateMachine['field'] = $config['field'] ?? 'status';
        $stateMachine['initial'] = $config['initial'] ?? null;

        // Parse transitions
        if (isset($config['transitions'])) {
            $stateMachine['transitions'] = $this->parseTransitions($config['transitions']);
        }

        // Parse guards
        if (isset($config['guards'])) {
            $stateMachine['guards'] = $this->parseGuards($config['guards']);
        }

        // Parse callbacks
        if (isset($config['callbacks'])) {
            $stateMachine['callbacks'] = $this->parseCallbacks($config['callbacks']);
        }

        // Parse states (if explicitly defined)
        if (isset($config['states'])) {
            $stateMachine['states'] = $this->parseStates($config['states']);
        } else {
            // Extract states from transitions
            $stateMachine['states'] = $this->extractStatesFromTransitions($stateMachine['transitions'] ?? []);
        }

        // Parse additional configuration
        $stateMachine['track_history'] = $config['track_history'] ?? true;
        $stateMachine['validate_transitions'] = $config['validate_transitions'] ?? true;
        $stateMachine['fire_events'] = $config['fire_events'] ?? true;

        return $stateMachine;
    }

    /**
     * Parse transitions configuration.
     *
     * @param array $transitions The transitions configuration
     * @return array The parsed transitions
     */
    private function parseTransitions(array $transitions): array
    {
        $parsedTransitions = [];

        foreach ($transitions as $transitionName => $transitionConfig) {
            if (is_array($transitionConfig)) {
                // Format: transition_name: [from_state1, from_state2, to_state]
                $fromStates = array_slice($transitionConfig, 0, -1);
                $toState = end($transitionConfig);
                
                $parsedTransitions[$transitionName] = [
                    'from' => $fromStates,
                    'to' => $toState,
                ];
            } elseif (is_string($transitionConfig)) {
                // Format: transition_name: "from_state -> to_state"
                $parts = explode('->', $transitionConfig);
                if (count($parts) === 2) {
                    $fromStates = array_map('trim', explode(',', trim($parts[0])));
                    $toState = trim($parts[1]);
                    
                    $parsedTransitions[$transitionName] = [
                        'from' => $fromStates,
                        'to' => $toState,
                    ];
                }
            }
        }

        return $parsedTransitions;
    }

    /**
     * Parse guards configuration.
     *
     * @param array $guards The guards configuration
     * @return array The parsed guards
     */
    private function parseGuards(array $guards): array
    {
        $parsedGuards = [];

        foreach ($guards as $transitionName => $guardMethod) {
            if (is_string($guardMethod)) {
                $parsedGuards[$transitionName] = [
                    'method' => $guardMethod,
                ];
            } elseif (is_array($guardMethod)) {
                $parsedGuards[$transitionName] = [
                    'method' => $guardMethod['method'] ?? null,
                    'parameters' => $guardMethod['parameters'] ?? [],
                ];
            }
        }

        return $parsedGuards;
    }

    /**
     * Parse callbacks configuration.
     *
     * @param array $callbacks The callbacks configuration
     * @return array The parsed callbacks
     */
    private function parseCallbacks(array $callbacks): array
    {
        $parsedCallbacks = [];

        foreach ($callbacks as $callbackName => $callbackMethod) {
            $type = $this->getCallbackType($callbackName);
            $transitionName = $this->getCallbackTransition($callbackName);

            if ($type && $transitionName) {
                if (!isset($parsedCallbacks[$transitionName])) {
                    $parsedCallbacks[$transitionName] = [];
                }

                if (is_string($callbackMethod)) {
                    $parsedCallbacks[$transitionName][$type] = [
                        'method' => $callbackMethod,
                    ];
                } elseif (is_array($callbackMethod)) {
                    $parsedCallbacks[$transitionName][$type] = [
                        'method' => $callbackMethod['method'] ?? null,
                        'parameters' => $callbackMethod['parameters'] ?? [],
                    ];
                }
            }
        }

        return $parsedCallbacks;
    }

    /**
     * Parse states configuration.
     *
     * @param array $states The states configuration
     * @return array The parsed states
     */
    private function parseStates(array $states): array
    {
        $parsedStates = [];

        foreach ($states as $stateName => $stateConfig) {
            if (is_string($stateConfig)) {
                $parsedStates[$stateName] = [
                    'label' => $stateConfig,
                ];
            } elseif (is_array($stateConfig)) {
                $parsedStates[$stateName] = [
                    'label' => $stateConfig['label'] ?? ucfirst($stateName),
                    'color' => $stateConfig['color'] ?? null,
                    'icon' => $stateConfig['icon'] ?? null,
                    'description' => $stateConfig['description'] ?? null,
                ];
            } else {
                $parsedStates[$stateName] = [
                    'label' => ucfirst($stateName),
                ];
            }
        }

        return $parsedStates;
    }

    /**
     * Extract states from transitions configuration.
     *
     * @param array $transitions The transitions configuration
     * @return array The extracted states
     */
    private function extractStatesFromTransitions(array $transitions): array
    {
        $states = [];

        foreach ($transitions as $transitionName => $transitionConfig) {
            // Add from states
            foreach ($transitionConfig['from'] as $fromState) {
                if (!isset($states[$fromState])) {
                    $states[$fromState] = [
                        'label' => ucfirst($fromState),
                    ];
                }
            }

            // Add to state
            $toState = $transitionConfig['to'];
            if (!isset($states[$toState])) {
                $states[$toState] = [
                    'label' => ucfirst($toState),
                ];
            }
        }

        return $states;
    }

    /**
     * Get callback type from callback name.
     *
     * @param string $callbackName The callback name
     * @return string|null The callback type
     */
    private function getCallbackType(string $callbackName): ?string
    {
        if (str_starts_with($callbackName, 'before_')) {
            return 'before';
        } elseif (str_starts_with($callbackName, 'after_')) {
            return 'after';
        }

        return null;
    }

    /**
     * Get transition name from callback name.
     *
     * @param string $callbackName The callback name
     * @return string|null The transition name
     */
    private function getCallbackTransition(string $callbackName): ?string
    {
        if (str_starts_with($callbackName, 'before_')) {
            return substr($callbackName, 7); // Remove 'before_'
        } elseif (str_starts_with($callbackName, 'after_')) {
            return substr($callbackName, 6); // Remove 'after_'
        }

        return null;
    }
} 