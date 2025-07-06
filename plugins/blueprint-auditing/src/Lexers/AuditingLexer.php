<?php

namespace BlueprintExtensions\Auditing\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Model;

class AuditingLexer implements Lexer
{
    /**
     * Parse auditing configuration from the tokens and add to the tree.
     *
     * @param array $tokens The parsed YAML tokens
     * @return array The modified tree with auditing data
     */
    public function analyze(array $tokens): array
    {
        $tree = [];

        if (isset($tokens['models'])) {
            foreach ($tokens['models'] as $name => $definition) {
                if (isset($definition['auditing'])) {
                    $tree['auditing'][$name] = $this->parseAuditingConfiguration($definition['auditing']);
                }
            }
        }

        return $tree;
    }

    /**
     * Parse the auditing configuration for a model.
     *
     * @param mixed $config The auditing configuration
     * @return array The parsed auditing configuration
     */
    private function parseAuditingConfiguration($config): array
    {
        // Handle simple boolean case
        if (is_bool($config) || $config === 'true' || $config === 'false') {
            return [
                'enabled' => $config === true || $config === 'true',
            ];
        }

        // Handle string shorthand
        if (is_string($config) && $config === 'auditing') {
            return [
                'enabled' => true,
            ];
        }

        // Handle detailed configuration
        if (is_array($config)) {
            $auditing = [
                'enabled' => true,
            ];

            // Parse events
            if (isset($config['events'])) {
                $auditing['events'] = is_array($config['events']) 
                    ? $config['events'] 
                    : explode(',', str_replace(' ', '', $config['events']));
            }

            // Parse excluded attributes
            if (isset($config['exclude'])) {
                $auditing['exclude'] = is_array($config['exclude']) 
                    ? $config['exclude'] 
                    : explode(',', str_replace(' ', '', $config['exclude']));
            }

            // Parse included attributes
            if (isset($config['include'])) {
                $auditing['include'] = is_array($config['include']) 
                    ? $config['include'] 
                    : explode(',', str_replace(' ', '', $config['include']));
            }

            // Parse strict mode
            if (isset($config['strict'])) {
                $auditing['strict'] = $config['strict'] === true || $config['strict'] === 'true';
            }

            // Parse threshold
            if (isset($config['threshold'])) {
                $auditing['threshold'] = (int) $config['threshold'];
            }

            // Parse console auditing
            if (isset($config['console'])) {
                $auditing['console'] = $config['console'] === true || $config['console'] === 'true';
            }

            // Parse empty values
            if (isset($config['empty_values'])) {
                $auditing['empty_values'] = $config['empty_values'] === true || $config['empty_values'] === 'true';
            }

            // Parse user configuration
            if (isset($config['user'])) {
                $auditing['user'] = $config['user'];
            }

            // Parse implementation
            if (isset($config['implementation'])) {
                $auditing['implementation'] = $config['implementation'];
            }

            // Parse resolvers
            if (isset($config['resolvers'])) {
                $auditing['resolvers'] = $config['resolvers'];
            }

            // Parse tags
            if (isset($config['tags'])) {
                $auditing['tags'] = is_array($config['tags']) 
                    ? $config['tags'] 
                    : explode(',', str_replace(' ', '', $config['tags']));
            }

            // Parse transformations
            if (isset($config['transformations'])) {
                $auditing['transformations'] = $config['transformations'];
            }

            // Parse audit relationship methods
            if (isset($config['audit_attach'])) {
                $auditing['audit_attach'] = $config['audit_attach'] === true || $config['audit_attach'] === 'true';
            }

            if (isset($config['audit_detach'])) {
                $auditing['audit_detach'] = $config['audit_detach'] === true || $config['audit_detach'] === 'true';
            }

            if (isset($config['audit_sync'])) {
                $auditing['audit_sync'] = $config['audit_sync'] === true || $config['audit_sync'] === 'true';
            }

            // Parse origin tracking configuration
            if (isset($config['origin_tracking'])) {
                $auditing['origin_tracking'] = $this->parseOriginTrackingConfiguration($config['origin_tracking']);
            }

            // Parse rewind configuration
            if (isset($config['rewind'])) {
                $auditing['rewind'] = $this->parseRewindConfiguration($config['rewind']);
            }

            return $auditing;
        }

        // Default case
        return [
            'enabled' => false,
        ];
    }

    /**
     * Parse the rewind configuration.
     *
     * @param mixed $config The rewind configuration
     * @return array The parsed rewind configuration
     */
    private function parseRewindConfiguration($config): array
    {
        // Handle simple boolean case
        if (is_bool($config) || $config === 'true' || $config === 'false') {
            return [
                'enabled' => $config === true || $config === 'true',
            ];
        }

        // Handle string shorthand
        if (is_string($config) && $config === 'rewind') {
            return [
                'enabled' => true,
            ];
        }

        // Handle detailed configuration
        if (is_array($config)) {
            $rewind = [
                'enabled' => true,
            ];

            // Parse methods to generate
            if (isset($config['methods'])) {
                $rewind['methods'] = is_array($config['methods']) 
                    ? $config['methods'] 
                    : explode(',', str_replace(' ', '', $config['methods']));
            } else {
                // Default methods
                $rewind['methods'] = ['rewindTo', 'rewindToDate', 'rewindSteps', 'getRewindableAudits'];
            }

            // Parse validation settings
            if (isset($config['validate'])) {
                $rewind['validate'] = $config['validate'] === true || $config['validate'] === 'true';
            }

            // Parse events to fire on rewind
            if (isset($config['events'])) {
                $rewind['events'] = is_array($config['events']) 
                    ? $config['events'] 
                    : explode(',', str_replace(' ', '', $config['events']));
            }

            // Parse attributes to exclude from rewind
            if (isset($config['exclude'])) {
                $rewind['exclude'] = is_array($config['exclude']) 
                    ? $config['exclude'] 
                    : explode(',', str_replace(' ', '', $config['exclude']));
            }

            // Parse attributes to include in rewind (overrides exclude)
            if (isset($config['include'])) {
                $rewind['include'] = is_array($config['include']) 
                    ? $config['include'] 
                    : explode(',', str_replace(' ', '', $config['include']));
            }

            // Parse confirmation requirement
            if (isset($config['require_confirmation'])) {
                $rewind['require_confirmation'] = $config['require_confirmation'] === true || $config['require_confirmation'] === 'true';
            }

            // Parse max rewind steps
            if (isset($config['max_steps'])) {
                $rewind['max_steps'] = (int) $config['max_steps'];
            }

            // Parse backup before rewind
            if (isset($config['backup_before_rewind'])) {
                $rewind['backup_before_rewind'] = $config['backup_before_rewind'] === true || $config['backup_before_rewind'] === 'true';
            }

            return $rewind;
        }

        // Default case
        return [
            'enabled' => false,
        ];
    }

    /**
     * Parse the origin tracking configuration.
     *
     * @param mixed $config The origin tracking configuration
     * @return array The parsed origin tracking configuration
     */
    private function parseOriginTrackingConfiguration($config): array
    {
        // Handle simple boolean case
        if (is_bool($config) || $config === 'true' || $config === 'false') {
            return [
                'enabled' => $config === true || $config === 'true',
            ];
        }

        // Handle string shorthand
        if (is_string($config) && $config === 'origin') {
            return [
                'enabled' => true,
            ];
        }

        // Handle detailed configuration
        if (is_array($config)) {
            $originTracking = [
                'enabled' => true,
            ];

            // Parse request tracking
            if (isset($config['track_request'])) {
                $originTracking['track_request'] = $config['track_request'] === true || $config['track_request'] === 'true';
            }

            // Parse session tracking
            if (isset($config['track_session'])) {
                $originTracking['track_session'] = $config['track_session'] === true || $config['track_session'] === 'true';
            }

            // Parse route tracking
            if (isset($config['track_route'])) {
                $originTracking['track_route'] = $config['track_route'] === true || $config['track_route'] === 'true';
            }

            // Parse controller action tracking
            if (isset($config['track_controller_action'])) {
                $originTracking['track_controller_action'] = $config['track_controller_action'] === true || $config['track_controller_action'] === 'true';
            }

            // Parse request data tracking
            if (isset($config['track_request_data'])) {
                $originTracking['track_request_data'] = $config['track_request_data'] === true || $config['track_request_data'] === 'true';
            }

            // Parse response data tracking
            if (isset($config['track_response_data'])) {
                $originTracking['track_response_data'] = $config['track_response_data'] === true || $config['track_response_data'] === 'true';
            }

            // Parse side effects tracking
            if (isset($config['track_side_effects'])) {
                $originTracking['track_side_effects'] = $config['track_side_effects'] === true || $config['track_side_effects'] === 'true';
            }

            // Parse causality chain tracking
            if (isset($config['track_causality_chain'])) {
                $originTracking['track_causality_chain'] = $config['track_causality_chain'] === true || $config['track_causality_chain'] === 'true';
            }

            // Parse audit grouping
            if (isset($config['group_audits'])) {
                $originTracking['group_audits'] = $config['group_audits'] === true || $config['group_audits'] === 'true';
            }

            // Parse excluded request fields
            if (isset($config['exclude_request_fields'])) {
                $originTracking['exclude_request_fields'] = is_array($config['exclude_request_fields']) 
                    ? $config['exclude_request_fields'] 
                    : explode(',', str_replace(' ', '', $config['exclude_request_fields']));
            }

            // Parse included request fields
            if (isset($config['include_request_fields'])) {
                $originTracking['include_request_fields'] = is_array($config['include_request_fields']) 
                    ? $config['include_request_fields'] 
                    : explode(',', str_replace(' ', '', $config['include_request_fields']));
            }

            // Parse origin types to track
            if (isset($config['track_origin_types'])) {
                $originTracking['track_origin_types'] = is_array($config['track_origin_types']) 
                    ? $config['track_origin_types'] 
                    : explode(',', str_replace(' ', '', $config['track_origin_types']));
            }

            // Parse custom resolvers
            if (isset($config['resolvers'])) {
                $originTracking['resolvers'] = $config['resolvers'];
            }

            return $originTracking;
        }

        // Default case
        return [
            'enabled' => false,
        ];
    }
} 