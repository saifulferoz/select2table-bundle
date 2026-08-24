<?php

declare(strict_types=1);

namespace SaifulFeroz\Select2TableBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SaifulFeroz\Select2TableBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfiguration(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, []);

        $this->assertSame(1, $config['minimum_input_length']);
        $this->assertFalse($config['scroll']);
        $this->assertSame(10, $config['page_limit']);
        $this->assertFalse($config['allow_clear']);
        $this->assertSame(250, $config['delay']);
        $this->assertSame('en', $config['language']);
        $this->assertSame('default', $config['theme']);
        $this->assertTrue($config['cache']);
        $this->assertSame(60000, $config['cache_timeout']);
        $this->assertNull($config['width']);
        $this->assertFalse($config['render_html']);
        $this->assertNull($config['table_name']);
        $this->assertNull($config['text_property']);
        $this->assertSame('id', $config['primary_key']);
        $this->assertFalse($config['allow_add']['enabled']);
        $this->assertSame(' (NEW)', $config['allow_add']['new_tag_text']);
        $this->assertSame('__', $config['allow_add']['new_tag_prefix']);
    }

    public function testCustomConfiguration(): void
    {
        $configuration = new Configuration();
        $processor = new Processor();
        $customConfig = [
            'saifulferoz_select2_table' => [
                'minimum_input_length' => 3,
                'page_limit' => 25,
                'scroll' => true,
                'allow_clear' => true,
                'delay' => 400,
                'language' => 'fr',
                'theme' => 'bootstrap-5',
                'cache' => false,
                'cache_timeout' => 0,
                'render_html' => true,
                'table_name' => 'tbl_users',
                'text_property' => 'email',
                'primary_key' => 'uuid',
                'allow_add' => [
                    'enabled' => true,
                    'new_tag_text' => ' [NEW]',
                    'new_tag_prefix' => 'tag_',
                ],
            ],
        ];

        $config = $processor->processConfiguration($configuration, $customConfig);

        $this->assertSame(3, $config['minimum_input_length']);
        $this->assertSame(25, $config['page_limit']);
        $this->assertTrue($config['scroll']);
        $this->assertTrue($config['allow_clear']);
        $this->assertSame(400, $config['delay']);
        $this->assertSame('fr', $config['language']);
        $this->assertSame('bootstrap-5', $config['theme']);
        $this->assertFalse($config['cache']);
        $this->assertSame(0, $config['cache_timeout']);
        $this->assertTrue($config['render_html']);
        $this->assertSame('tbl_users', $config['table_name']);
        $this->assertSame('email', $config['text_property']);
        $this->assertSame('uuid', $config['primary_key']);
        $this->assertTrue($config['allow_add']['enabled']);
        $this->assertSame(' [NEW]', $config['allow_add']['new_tag_text']);
        $this->assertSame('tag_', $config['allow_add']['new_tag_prefix']);
    }
}
