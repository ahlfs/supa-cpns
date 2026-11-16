<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Icon;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Guards the house rule: every icon a view asks for is a real SVG file in
 * resources/icons. A typo in a name would otherwise only show up as a broken page
 * on production, and an emoji would never be caught at all.
 */
final class IconsTest extends TestCase
{
    public function test_every_icon_named_in_a_view_exists_as_an_svg_file(): void
    {
        $names = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all('/<x-icon\s+name="([a-z0-9-]+)"/', $file->getContents(), $matches);
            $names = array_merge($names, $matches[1]);

            preg_match_all("/'icon' => '([a-z0-9-]+)'/", $file->getContents(), $matches);
            $names = array_merge($names, $matches[1]);
        }

        $this->assertNotEmpty($names, 'No icon usages were found at all — the scan is broken.');

        foreach (array_unique($names) as $name) {
            $this->assertTrue(Icon::exists($name), "Icon [{$name}] is referenced but resources/icons/{$name}.svg does not exist.");
        }
    }

    public function test_no_view_uses_an_emoji_as_an_icon(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertSame(
                0,
                preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $file->getContents()),
                "A view contains an emoji, which the house rule forbids: {$file->getRelativePathname()}",
            );
        }
    }
}
