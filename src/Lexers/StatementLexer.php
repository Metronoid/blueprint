<?php

namespace Blueprint\Lexers;

use Blueprint\Contracts\Lexer;
use Blueprint\Models\Statements\DispatchStatement;
use Blueprint\Models\Statements\EloquentStatement;
use Blueprint\Models\Statements\FireStatement;
use Blueprint\Models\Statements\InertiaStatement;
use Blueprint\Models\Statements\QueryStatement;
use Blueprint\Models\Statements\RedirectStatement;
use Blueprint\Models\Statements\RenderStatement;
use Blueprint\Models\Statements\ResourceStatement;
use Blueprint\Models\Statements\RespondStatement;
use Blueprint\Models\Statements\SendStatement;
use Blueprint\Models\Statements\SessionStatement;
use Blueprint\Models\Statements\ValidateStatement;
use Illuminate\Support\Str;

class StatementLexer implements Lexer
{
    public function analyze(array $tokens): array
    {
        $statements = [];

        foreach ($tokens as $command => $statement) {
            // If $statement is an array, flatten or convert to string
            if (is_array($statement)) {
                if (count($statement) === 1 && isset($statement[0])) {
                    $statement = $statement[0];
                } else {
                    $statement = implode(', ', array_map(function($v) {
                        return is_scalar($v) ? $v : var_export($v, true);
                    }, $statement));
                }
            } elseif (is_object($statement)) {
                if (method_exists($statement, '__toString')) {
                    $statement = (string)$statement;
                } else {
                    $statement = var_export($statement, true);
                }
            }
            $statements[] = match ($command) {
                'dispatch' => $this->analyzeDispatch($statement),
                'fire' => $this->analyzeEvent($statement),
                'flash', 'store' => new SessionStatement($command, $statement),
                'inertia' => $this->analyzeInertia($statement),
                'notify' => $this->analyzeNotify($statement),
                'query' => $this->analyzeQuery($statement),
                'redirect' => $this->analyzeRedirect($statement),
                'render' => $this->analyzeRender($statement),
                'resource' => $this->analyzeResource($statement),
                'respond' => $this->analyzeRespond($statement),
                'save', 'delete', 'find' => new EloquentStatement($command, $statement),
                'send' => $this->analyzeSend($statement),
                'update' => $this->analyzeUpdate($statement),
                'validate' => $this->analyzeValidate($statement),
                default => $this->analyzeDefault($command, $statement),
            };
        }

        return array_filter($statements);
    }

    private function analyzeInertia(string $statement): InertiaStatement
    {
        [$view, $data] = $this->parseWithStatement($statement);

        return new InertiaStatement($view, $data);
    }

    private function analyzeRender(string $statement): RenderStatement
    {
        [$view, $data] = $this->parseWithStatement($statement);

        return new RenderStatement($view, $data);
    }

    private function analyzeEvent(string $statement): FireStatement
    {
        [$event, $data] = $this->parseWithStatement($statement);

        return new FireStatement($event, $data);
    }

    private function analyzeDispatch(string $statement): DispatchStatement
    {
        [$job, $data] = $this->parseWithStatement($statement);

        return new DispatchStatement($job, $data);
    }

    private function analyzeRedirect(string $statement): RedirectStatement
    {
        [$route, $data] = $this->parseWithStatement($statement);

        return new RedirectStatement($route, $data);
    }

    private function analyzeRespond(string $statement): RespondStatement
    {
        return new RespondStatement($statement);
    }

    private function analyzeSend($statement): SendStatement
    {
        $to = null;
        $view = null;

        $found = preg_match('/\\s+to:(\\S+)/', $statement, $matches);
        if ($found) {
            $to = $matches[1];
            $statement = str_replace($matches[0], '', $statement);
        }

        $found = preg_match('/\\s+view:(\\S+)/', $statement, $matches);
        if ($found) {
            $view = $matches[1];
            $statement = str_replace($matches[0], '', $statement);
        }

        [$object, $with] = $this->extractTokens($statement, 2);

        $data = [];
        if (!empty($with)) {
            $data = preg_split('/,([ \t]+)?/', substr($with, 5));
        }

        $type = SendStatement::TYPE_MAIL;
        if (Str::endsWith($object, 'Notification')) {
            $type = SendStatement::TYPE_NOTIFICATION_WITH_FACADE;
        }

        return new SendStatement($object, $to, $data, $type, $view);
    }

    private function analyzeNotify($statement): SendStatement
    {
        [$model, $notification, $with] = $this->extractTokens($statement, 3);

        $data = [];
        if (!empty($with)) {
            $data = preg_split('/,([ \t]+)?/', substr($with, 5));
        }

        return new SendStatement($notification, $model, $data, SendStatement::TYPE_NOTIFICATION_WITH_MODEL);
    }

    private function analyzeValidate($statement): ValidateStatement
    {
        return new ValidateStatement(preg_split('/,([ \t]+)?/', $statement));
    }

    private function parseWithStatement(string $statement): array
    {
        [$object, $with] = $this->extractTokens($statement, 2);

        $data = [];

        if (!empty($with)) {
            $data = preg_split('/,([ \t]+)?/', substr($with, 5));
        }

        return [$object, $data];
    }

    private function extractTokens(string $statement, int $limit = -1): array
    {
        return array_pad(preg_split('/[ \t]+/', $statement, $limit), $limit, null);
    }

    private function analyzeQuery($statement): QueryStatement
    {
        if ($statement === 'all') {
            return new QueryStatement('all');
        }

        $found = preg_match('/^all:(\\S+)$/', $statement, $matches);
        if ($found) {
            return new QueryStatement('all', [$matches[1]]);
        }

        if (Str::contains($statement, 'pluck:')) {
            return new QueryStatement('pluck', $this->extractTokens($statement));
        }

        $found = preg_match('/\b(count|exists)\b/', $statement, $matches);
        if ($found) {
            return new QueryStatement($matches[1], $this->extractTokens(trim(str_replace($matches[1], '', $statement))));
        }

        return new QueryStatement('get', $this->extractTokens($statement));
    }

    private function analyzeResource($statement): ResourceStatement
    {
        $reference = $statement;
        $collection = null;

        if (Str::contains($statement, ':')) {
            $collection = Str::before($reference, ':');
            $reference = Str::after($reference, ':');
        }

        return new ResourceStatement($reference, !is_null($collection), $collection === 'paginate');
    }

    private function analyzeUpdate($statement): EloquentStatement
    {
        if (!Str::contains($statement, ',')) {
            return new EloquentStatement('update', $statement);
        }

        $columns = preg_split('/,([ \t]+)?/', $statement);

        return new EloquentStatement('update', null, $columns);
    }

    private function analyzeDefault(string $command, string $statement)
    {
        if (fnmatch('fire-*', $command)) {
            return $this->analyzeEvent($statement);
        }

        if (fnmatch('dispatch-*', $command)) {
            return $this->analyzeDispatch($statement);
        }

        if (fnmatch('send-*', $command)) {
            return $this->analyzeSend($statement);
        }

        if (fnmatch('notify-*', $command)) {
            return $this->analyzeNotify($statement);
        }
    }
}
