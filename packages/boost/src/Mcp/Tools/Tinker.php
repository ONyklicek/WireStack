<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Throwable;

#[Name('tinker')]
#[Description('Evaluate a snippet of PHP within the application context and return its result and any echoed output. Disabled by default; enable with WIRE_BOOST_TINKER=true.')]
class Tinker extends BoostTool
{
    protected function run(Request $request): Response
    {
        if (! config('wire-boost.tools.tinker', false)) {
            return Response::error('The tinker tool is disabled. Set WIRE_BOOST_TINKER=true to enable it.');
        }

        $code = trim((string) $request->get('code'));
        $code = rtrim($code, ';');

        if ($code === '') {
            return Response::error('No code provided.');
        }

        try {
            ob_start();
            /** @psalm-suppress ForbiddenCode */
            $result = eval('return '.$code.';');
            $output = (string) ob_get_clean();

            return $this->json([
                'result' => $this->stringify($result),
                'output' => $output,
            ]);
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return Response::error($e->getMessage());
        }
    }

    private function stringify(mixed $result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if (is_scalar($result) || $result === null) {
            return var_export($result, true);
        }

        return (string) json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'code' => $schema->string()->description('A PHP expression to evaluate, e.g. "User::count()".')->required(),
        ];
    }
}
