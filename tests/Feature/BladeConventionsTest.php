<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Blade directives such as @js and @json are not compiled inside the opening
 * tag of a component (<x-…>): the literal text reaches the browser and the
 * Alpine expression breaks. Use {{ \Illuminate\Support\Js::from($value) }} there.
 * (Found in a real browser run: the staff status buttons did nothing.)
 */
class BladeConventionsTest extends TestCase
{
    public function test_no_directive_appears_inside_a_component_opening_tag(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            $source = File::get($file->getPathname());

            foreach ($this->openingTags($source) as $tag) {
                if (preg_match('/@(js|json|lang|choice|if|class|style|checked|selected|disabled)\b/', $tag, $match)) {
                    $offenders[] = $file->getRelativePathname().': @'.$match[1].' in '.mb_substr(preg_replace('/\s+/', ' ', $tag), 0, 90);
                }
            }
        }

        $this->assertSame([], $offenders);
    }

    /**
     * The opening tags of components, honouring quoted attribute values (which may contain ">" in JavaScript arrows).
     *
     * @return list<string>
     */
    private function openingTags(string $source): array
    {
        $tags = [];
        $length = strlen($source);

        $offset = 0;
        while (($start = strpos($source, '<x-', $offset)) !== false) {
            $quote = null;
            $i = $start;
            for (; $i < $length; $i++) {
                $char = $source[$i];
                if ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    }
                } elseif ($char === '"' || $char === "'") {
                    $quote = $char;
                } elseif ($char === '>') {
                    break;
                }
            }
            $tags[] = substr($source, $start, $i - $start + 1);
            $offset = $i + 1;
        }

        return $tags;
    }
}
