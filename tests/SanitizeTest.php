<?php

namespace UrlParameterStripper\Tests;

use PHPUnit\Framework\TestCase;
use UpsTestState;

class SanitizeTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // Load code under test
        require_once __DIR__ . '/../includes/sanitize.php';
    }

    protected function setUp(): void
    {
        // Reset options before each test
        UpsTestState::$options = [
            UPS_OPTION_KEY => 'utm_*,gclid,ref,foo=bar',
            'ups_fragment_patterns' => '*'
        ];
    }

    public function testStripKeyMatch()
    {
        $this->assertEquals(
            'https://example.com/',
            url_parameter_stripper_strip_url('https://example.com/?utm_source=123')
        );
        $this->assertEquals(
            'https://example.com/',
            url_parameter_stripper_strip_url('https://example.com/?gclid=abc')
        );
    }

    public function testStripKeyValueMatch()
    {
        // foo=bar is in default mock options
        $this->assertEquals(
            'https://example.com/',
            url_parameter_stripper_strip_url('https://example.com/?foo=bar')
        );
        
        // foo=baz mismatch
        $this->assertEquals(
            'https://example.com/?foo=baz',
            url_parameter_stripper_strip_url('https://example.com/?foo=baz')
        );
    }

    public function testStripFragments()
    {
        // wildcard * is set in setUp
        $this->assertEquals(
            'https://example.com/',
            url_parameter_stripper_strip_url('https://example.com/#somefrag')
        );

        // Test specific fragment pattern
        UpsTestState::$options['ups_fragment_patterns'] = ':~:text=*';
        
        $this->assertEquals(
            'https://example.com/',
            url_parameter_stripper_strip_url('https://example.com/#:~:text=hello')
        );
        
        // Should not strip unmatched fragment
        $this->assertEquals(
            'https://example.com/#other',
            url_parameter_stripper_strip_url('https://example.com/#other')
        );
    }

    public function testSanitizeTextUrls()
    {
        // Should strip inside href
        $input = 'Check <a href="https://example.com/?utm_source=test">link</a>.';
        $expected = 'Check <a href="https://example.com/">link</a>.';
        $this->assertEquals($expected, url_parameter_stripper_sanitize_text_urls($input));

        // Should NOT strip plain text URL
        $input = 'Check https://example.com/?utm_source=test logic.';
        $expected = 'Check https://example.com/?utm_source=test logic.';
        $this->assertEquals($expected, url_parameter_stripper_sanitize_text_urls($input));
        
        // Should handle single quotes
        $input = "Link <a href='https://example.com/?utm_medium=email'>here</a>";
        $expected = "Link <a href='https://example.com/'>here</a>";
        $this->assertEquals($expected, url_parameter_stripper_sanitize_text_urls($input));
    }

    public function testRelativeUrls()
    {
        // /page?utm_...
        $input = '<a href="/page?utm_medium=email">Link</a>';
        $expected = '<a href="/page">Link</a>';
        $this->assertEquals($expected, url_parameter_stripper_sanitize_text_urls($input));
    }
    
    public function testMultipleParams()
    {
        $this->assertEquals(
            'https://example.com/?keep=me',
            url_parameter_stripper_strip_url('https://example.com/?foo=bar&keep=me')
        );
        
        $this->assertEquals(
            'https://example.com/?keep=me',
            url_parameter_stripper_strip_url('https://example.com/?utm_source=x&keep=me&gclid=y')
        );
    }

    public function testHtmlEntitiesInUrl()
    {
        // Regression test for "amp%3B" corruption
        // Input has &amp; separators. We expect them to be handled as separators, 
        // and parameters to be stripped cleanly without double-encoding artifacts.
        
        $input = 'https://www.link.com/abc?utm_source=chatgpt.com&amp;utm_medium=ooo&amp;aaa=bbb';
        // utm_* matches default patterns in setUp
        // aaa is not matched
        
        // If we strip utm_source and utm_medium, we should be left with aaa=bbb.
        // Note: ups_strip_url returns a "clean" URL. If the input was entity encoded, 
        // the output usually comes out as a plain URL unless re-encoded. 
        // `ups_strip_url` uses `http_build_query` which does standard URL encoding, but doesn't do HTML entity encoding (no &amp;)
        // unless arg_separator is set to &amp;. PHP default is usually &.
        
        $expected = 'https://www.link.com/abc?aaa=bbb';
        
        $this->assertEquals(
            $expected,
            url_parameter_stripper_strip_url($input)
        );
    }

    public function testSlashedAttributes()
    {
        // Regression test for WordPress slashed data (e.g. content_save_pre)
        // <a href=\"https://.../?utm_source=...\" ...>
        
        $input = 'Check <a href=\"https://example.com/?utm_source=fail\">link</a>';
        $expected = 'Check <a href=\"https://example.com/\">link</a>';
        
        $this->assertEquals(
            $expected,
            url_parameter_stripper_sanitize_text_urls($input)
        );
        
        // Also check single quotes escaped
        $input2 = "Link <a href=\'https://example.com/?utm_source=fail\'>here</a>";
        $expected2 = "Link <a href=\'https://example.com/\'>here</a>";
        
        $this->assertEquals(
            $expected2,
            url_parameter_stripper_sanitize_text_urls($input2)
        );
    }
}
