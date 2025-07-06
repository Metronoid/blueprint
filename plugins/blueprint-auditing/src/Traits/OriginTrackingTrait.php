<?php

namespace BlueprintExtensions\Auditing\Traits;

use OwenIt\Auditing\Models\Audit;
use BlueprintExtensions\Auditing\Resolvers\RequestIdResolver;
use BlueprintExtensions\Auditing\Resolvers\RouteNameResolver;
use BlueprintExtensions\Auditing\Resolvers\ControllerActionResolver;
use BlueprintExtensions\Auditing\Resolvers\RequestDataResolver;
use BlueprintExtensions\Auditing\Resolvers\OriginTypeResolver;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

trait OriginTrackingTrait
{
    /**
     * Get the audit data with origin tracking information.
     *
     * @return array
     */
    public function generateTags(): array
    {
        $tags = parent::generateTags();
        
        if (!$this->isOriginTrackingEnabled()) {
            return $tags;
        }
        
        // Add origin tracking tags
        $originTags = $this->generateOriginTags();
        
        return array_merge($tags, $originTags);
    }
    
    /**
     * Generate origin tracking tags.
     *
     * @return array
     */
    protected function generateOriginTags(): array
    {
        $tags = [];
        
        // Add request ID if tracking is enabled
        if ($this->shouldTrackRequest()) {
            $requestId = RequestIdResolver::resolve();
            if ($requestId) {
                $tags['request_id'] = $requestId;
            }
        }
        
        // Add session ID if tracking is enabled
        if ($this->shouldTrackSession()) {
            $sessionId = session()->getId();
            if ($sessionId) {
                $tags['session_id'] = $sessionId;
            }
        }
        
        // Add route name if tracking is enabled
        if ($this->shouldTrackRoute()) {
            $routeName = RouteNameResolver::resolve();
            if ($routeName) {
                $tags['route_name'] = $routeName;
            }
        }
        
        // Add controller action if tracking is enabled
        if ($this->shouldTrackControllerAction()) {
            $controllerAction = ControllerActionResolver::resolve();
            if ($controllerAction) {
                $tags['controller_action'] = $controllerAction;
            }
        }
        
        // Add HTTP method
        if (App::bound('request')) {
            $request = App::make('request');
            if ($request) {
                $tags['http_method'] = $request->method();
            }
        }
        
        // Add request data if tracking is enabled
        if ($this->shouldTrackRequestData()) {
            $requestData = RequestDataResolver::resolve(
                $this->getExcludeRequestFields(),
                $this->getIncludeRequestFields()
            );
            if ($requestData) {
                $tags['request_data'] = json_encode($requestData);
            }
        }
        
        // Add origin type and context
        $originType = OriginTypeResolver::resolve();
        $tags['origin_type'] = $originType;
        
        $originContext = OriginTypeResolver::getContext($originType);
        if ($originContext) {
            $tags['origin_context'] = $originContext;
        }
        
        // Add audit group ID if grouping is enabled
        if ($this->shouldGroupAudits()) {
            $tags['audit_group_id'] = $this->getAuditGroupId();
        }
        
        // Add causality chain if tracking is enabled
        if ($this->shouldTrackCausalityChain()) {
            $causalityChain = $this->getCausalityChain();
            if ($causalityChain) {
                $tags['causality_chain'] = $causalityChain;
            }
        }
        
        return $tags;
    }
    
    /**
     * Check if origin tracking is enabled for this model.
     *
     * @return bool
     */
    protected function isOriginTrackingEnabled(): bool
    {
        return $this->originTrackingEnabled ?? false;
    }
    
    /**
     * Check if request tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackRequest(): bool
    {
        return $this->trackRequest ?? false;
    }
    
    /**
     * Check if session tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackSession(): bool
    {
        return $this->trackSession ?? false;
    }
    
    /**
     * Check if route tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackRoute(): bool
    {
        return $this->trackRoute ?? false;
    }
    
    /**
     * Check if controller action tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackControllerAction(): bool
    {
        return $this->trackControllerAction ?? false;
    }
    
    /**
     * Check if request data tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackRequestData(): bool
    {
        return $this->trackRequestData ?? false;
    }
    
    /**
     * Check if response data tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackResponseData(): bool
    {
        return $this->trackResponseData ?? false;
    }
    
    /**
     * Check if side effects tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackSideEffects(): bool
    {
        return $this->trackSideEffects ?? false;
    }
    
    /**
     * Check if causality chain tracking is enabled.
     *
     * @return bool
     */
    protected function shouldTrackCausalityChain(): bool
    {
        return $this->trackCausalityChain ?? false;
    }
    
    /**
     * Check if audit grouping is enabled.
     *
     * @return bool
     */
    protected function shouldGroupAudits(): bool
    {
        return $this->groupAudits ?? false;
    }
    
    /**
     * Get excluded request fields.
     *
     * @return array
     */
    protected function getExcludeRequestFields(): array
    {
        return $this->excludeRequestFields ?? [];
    }
    
    /**
     * Get included request fields.
     *
     * @return array
     */
    protected function getIncludeRequestFields(): array
    {
        return $this->includeRequestFields ?? [];
    }
    
    /**
     * Get the audit group ID for this request.
     *
     * @return string
     */
    protected function getAuditGroupId(): string
    {
        if (session()->has('audit_group_id')) {
            return session()->get('audit_group_id');
        }
        
        $groupId = Str::uuid()->toString();
        session()->put('audit_group_id', $groupId);
        
        return $groupId;
    }
    
    /**
     * Get the causality chain for this change.
     *
     * @return string|null
     */
    protected function getCausalityChain(): ?string
    {
        // This would track the chain of events that led to this change
        // Implementation would depend on your application's event system
        return null;
    }
    
    /**
     * Track side effects of this change.
     *
     * @param array $sideEffects
     * @return void
     */
    public function trackSideEffects(array $sideEffects): void
    {
        if (!$this->shouldTrackSideEffects()) {
            return;
        }
        
        // Store side effects in the session for later retrieval
        $currentSideEffects = session()->get('audit_side_effects', []);
        $currentSideEffects[] = $sideEffects;
        session()->put('audit_side_effects', $currentSideEffects);
    }
    
    /**
     * Get side effects for the current request.
     *
     * @return array
     */
    public function getSideEffects(): array
    {
        return session()->get('audit_side_effects', []);
    }
    
    /**
     * Clear side effects for the current request.
     *
     * @return void
     */
    public function clearSideEffects(): void
    {
        session()->forget('audit_side_effects');
    }
    
    /**
     * Link this audit to a parent audit (for side effects).
     *
     * @param Audit $parentAudit
     * @return void
     */
    public function linkToParentAudit(Audit $parentAudit): void
    {
        // This would be called when creating a side effect audit
        // to link it back to the original change
    }
} 