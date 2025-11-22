<?php

namespace UrlParameterStripper\Tests;

use PHPUnit\Framework\TestCase;

class PluginStructureTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = realpath(__DIR__ . '/..');
    }

    public function testRequiredFilesExist(): void
    {
        $required = [
            'url-parameter-stripper.php',
            'readme.txt',
        ];

        foreach ($required as $relativePath) {
            $absolutePath = $this->root . DIRECTORY_SEPARATOR . $relativePath;
            $this->assertFileExists(
                $absolutePath,
                sprintf('Missing required file: %s', $relativePath)
            );
        }
    }

    public function testPluginHeaderContainsPluginName(): void
    {
        $pluginFile = $this->root . '/url-parameter-stripper.php';
        $this->assertFileExists($pluginFile, 'Plugin file missing');

        $contents = file_get_contents($pluginFile);
        $this->assertMatchesRegularExpression(
            '/Plugin Name:\s*URL Parameter Stripper/i',
            $contents,
            'Plugin header must declare "Plugin Name: URL Parameter Stripper"'
        );
    }
}
