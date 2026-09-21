<?php

declare(strict_types=1);

namespace Shimmie2;

use function MicroHTML\{A,B,DIV,joinHTML};

use MicroHTML\HTMLElement;

class Danbooru2CustomCommonElementsTheme extends CommonElementsTheme
{
    /**
     * CUSTOMIZATION START: Override build_thumb to add video duration marker
     */
    public function build_thumb(Post $image): HTMLElement
    {
        // Get the parent's thumbnail
        $thumb = parent::build_thumb($image);
        
        $mime = $image->get_mime();
        $mime_str = (string)$mime;
        $is_video = strpos($mime_str, "video/") === 0;
        
        if (!$is_video) {
            return $thumb;
        }
        
        // Extract attributes and children using reflection
        $reflection = new \ReflectionClass($thumb);
        $attrs_prop = $reflection->getProperty('attrs');
        $children_prop = $reflection->getProperty('children');
        $attrs_prop->setAccessible(true);
        $children_prop->setAccessible(true);
        
        $attrs = $attrs_prop->getValue($thumb);
        $children = $children_prop->getValue($thumb);
        
        // Add video class
        $attrs['class'] = ($attrs['class'] ?? '') . ' video';
        
        // Rebuild the anchor with modified attributes
        $thumb = A($attrs, ...$children);
        
        // Try to add duration and audio badge
        if ($image->length !== null && $image->length > 0) {
            $total_seconds = (int)floor($image->length / 1000);
            $hours = (int)floor($total_seconds / 3600);
            $minutes = (int)floor(($total_seconds % 3600) / 60);
            $seconds = $total_seconds % 60;

            if ($hours > 0) {
                $duration_text = sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
            } else {
                $duration_text = sprintf("%d:%02d", $minutes, $seconds);
            }
            
            // Add audio icon if video has audio
            if ($image->audio === true) {
                $duration_text .= " 🔊";
            }
            
            $duration_badge = DIV(
                ["class" => "video-duration"],
                $duration_text
            );
            $thumb->appendChild($duration_badge);
        }
        
        return $thumb;
    }
    /* CUSTOMIZATION END */

    public function display_paginator(string $base, ?QueryArray $query, int $page_number, int $total_pages, bool $show_random = false): void
    {
        if ($total_pages === 0) {
            $total_pages = 1;
        }
        $body = $this->build_paginator($page_number, $total_pages, $base, $query);
        Ctx::$page->add_block(new Block(null, $body, "main", 90));
    }

    private function gen_page_link(string $base_url, ?QueryArray $query, int $page, string $name): HTMLElement
    {
        return A(["href" => make_link("$base_url/$page", $query)], $name);
    }

    private function gen_page_link_block(string $base_url, ?QueryArray $query, int $page, int $current_page, string $name): HTMLElement
    {
        if ($page === $current_page) {
            $paginator = B($page);
        } else {
            $paginator = $this->gen_page_link($base_url, $query, $page, $name);
        }
        return $paginator;
    }

    private function build_paginator(int $current_page, int $total_pages, string $base_url, ?QueryArray $query): HTMLElement
    {
        $next = $current_page + 1;
        $prev = $current_page - 1;

        $at_start = ($current_page <= 3 || $total_pages <= 3);
        $at_end = ($current_page >= $total_pages - 2);

        $first_html  = $at_start ? "" : $this->gen_page_link($base_url, $query, 1, "1");
        $prev_html   = $at_start ? "" : $this->gen_page_link($base_url, $query, $prev, "<<");
        $next_html   = $at_end ? "" : $this->gen_page_link($base_url, $query, $next, ">>");
        $last_html   = $at_end ? "" : $this->gen_page_link($base_url, $query, $total_pages, "$total_pages");

        $start = $current_page - 2 > 1 ? $current_page - 2 : 1;
        $end   = $current_page + 2 <= $total_pages ? $current_page + 2 : $total_pages;

        $pages = [];
        foreach (range($start, $end) as $i) {
            $pages[] = $this->gen_page_link_block($base_url, $query, $i, $current_page, (string)$i);
        }
        $pages_html = joinHTML(" ", $pages);

        if ($start > 2) {
            $pdots = "...";
        } else {
            $pdots = "";
        }

        if ($total_pages > $end + 1) {
            $ndots = "...";
        } else {
            $ndots = "";
        }

        return DIV(["id" => "paginator"], joinHTML(" ", [$prev_html, $first_html, $pdots, $pages_html, $ndots, $last_html, $next_html]));
    }
}
