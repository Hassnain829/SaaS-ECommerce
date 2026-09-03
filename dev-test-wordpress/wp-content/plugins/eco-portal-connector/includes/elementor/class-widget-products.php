<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

final class Eco_Portal_Elementor_Products_Widget extends Widget_Base
{
    public function get_name(): string
    {
        return 'eco_portal_products';
    }

    public function get_title(): string
    {
        return __('Portal Products', 'eco-portal-connector');
    }

    public function get_icon(): string
    {
        return 'eicon-products';
    }

    public function get_categories(): array
    {
        return ['eco-portal'];
    }

    public function get_keywords(): array
    {
        return ['eco', 'portal', 'products', 'catalog', 'jerky', 'shop'];
    }

    protected function register_controls(): void
    {
        $this->start_controls_section('content', [
            'label' => __('Section', 'eco-portal-connector'),
        ]);

        $this->add_control('title', [
            'label' => __('Heading', 'eco-portal-connector'),
            'type' => Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->add_control('layout', [
            'label' => __('Layout', 'eco-portal-connector'),
            'type' => Controls_Manager::SELECT,
            'default' => 'cards',
            'options' => [
                'cards' => __('Price cards', 'eco-portal-connector'),
                'grid' => __('Shop grid', 'eco-portal-connector'),
                'hero' => __('Hero slides', 'eco-portal-connector'),
                'topsellers' => __('Top sellers', 'eco-portal-connector'),
            ],
        ]);

        $this->add_control('tag', [
            'label' => __('Portal tag', 'eco-portal-connector'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => 'homepage-hero',
            'description' => __('Show only products with this tag from the merchant portal. Leave empty to use product IDs or all products.', 'eco-portal-connector'),
        ]);

        $this->add_control('ids', [
            'label' => __('Product IDs', 'eco-portal-connector'),
            'type' => Controls_Manager::TEXT,
            'placeholder' => '12,15,18',
            'description' => __('Optional. Comma-separated portal product IDs. Overrides tag when set.', 'eco-portal-connector'),
        ]);

        $this->add_control('limit', [
            'label' => __('Limit', 'eco-portal-connector'),
            'type' => Controls_Manager::NUMBER,
            'default' => 6,
            'min' => 1,
            'max' => 50,
        ]);

        $this->end_controls_section();
    }

    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $tag = sanitize_text_field((string) ($settings['tag'] ?? ''));
        $ids = sanitize_text_field((string) ($settings['ids'] ?? ''));
        $layout = sanitize_key((string) ($settings['layout'] ?? 'cards'));
        $title = sanitize_text_field((string) ($settings['title'] ?? ''));
        $limit = max(1, min(50, (int) ($settings['limit'] ?? 6)));

        echo do_shortcode(sprintf(
            '[eco_portal_products tag="%s" ids="%s" layout="%s" title="%s" limit="%d"]',
            esc_attr($tag),
            esc_attr($ids),
            esc_attr($layout),
            esc_attr($title),
            $limit
        ));
    }
}
