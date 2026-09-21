<?php

declare(strict_types=1);

namespace Shimmie2;

use MicroHTML\HTMLElement;

use function MicroHTML\{BR, DIV, INPUT, LI, UL, emptyHTML, rawHTML};

class Danbooru2CustomViewPostTheme extends ViewPostTheme
{
    /**
     * Editor rows (keyed by their SHM_POST_INFO title) which are only shown
     * while editing, since the same info is already shown elsewhere on the
     * page (sidebar blocks, caption panel)
     */
    private const EDIT_ONLY_ROWS = ["Title", "Description", "Uploader", "Source Link", "Tags", "Rating"];

    /**
     * Editor rows which are dropped entirely, since they are rendered in the
     * "Information" sidebar block instead
     */
    private const HIDDEN_ROWS = ["Views"];

    /**
     * @param HTMLElement[] $editor_parts
     * @param HTMLElement[] $sidebar_parts
     */
    public function display_page(Post $image, array $editor_parts, array $sidebar_parts): void
    {
        Ctx::$page->set_heading($image->get_tag_list());
        Ctx::$page->add_block(new Block("Search", $this->build_navigation($image), "left", 0));
        $this->add_tag_blocks($image);
        Ctx::$page->add_block(new Block("Information", $this->build_stats($image), "left", 15));

        $caption = $this->build_caption($image);
        if ($caption !== null) {
            Ctx::$page->add_block(new Block(null, $caption, "main", 12, "PostCaption"));
        }
        Ctx::$page->add_block(new Block(null, $this->build_info($image, $this->filter_editor_parts($editor_parts)), "main", 15));
    }

    protected function build_navigation(Post $image): HTMLElement
    {
        return SHM_FORM(
            action: search_link(),
            method: 'GET',
            children: [
                INPUT([
                    "name" => 'search',
                    "type" => 'text',
                    "class" => 'autocomplete_tags',
                    "style" => 'width:75%'
                ]),
                INPUT([
                    "type" => 'submit',
                    "value" => 'Go',
                    "style" => 'width:20%'
                ]),
            ]
        );
    }

    protected function build_stats(Post $image): HTMLElement
    {
        $stats = parent::build_stats($image);
        if (ImageViewCounterInfo::is_enabled() && Ctx::$user->can(ImageViewCounterPermission::SEE_IMAGE_VIEW_COUNTS)) {
            $views = (int)Ctx::$database->get_one(
                "SELECT COUNT(*) FROM image_views WHERE image_id = :image_id",
                ["image_id" => $image->id]
            );
            $stats = emptyHTML($stats, BR(), "Views: $views");
        }
        return $stats;
    }

    /**
     * Title (bold) with the formatted description underneath, or null if the
     * post has neither
     */
    private function build_caption(Post $image): ?HTMLElement
    {
        $title = PostTitlesInfo::is_enabled() ? PostTitles::get_title($image) : "";
        $description = PostDescriptionInfo::is_enabled() ? (string)Ctx::$database->get_one(
            "SELECT description FROM image_descriptions WHERE image_id = :id",
            ["id" => $image->id]
        ) : "";
        // The description editor is prefilled with "None" when unset, so
        // saving the form without touching it stores that literally
        if (trim($description) === "None") {
            $description = "";
        }

        if ($title === "" && $description === "") {
            return null;
        }

        $caption = DIV(["class" => "post-caption"]);
        if ($title !== "") {
            $caption->appendChild(DIV(["class" => "post-caption-title"], $title));
        }
        if ($description !== "") {
            $formatted = send_event(new TextFormattingEvent($description))->formatted;
            $caption->appendChild(DIV(["class" => "post-caption-description"], rawHTML($formatted)));
        }
        return $caption;
    }

    /**
     * One sidebar block per tag category (sorted alphabetically by the category's
     * display name), with uncategorised tags in a "General" block at the end.
     */
    private function add_tag_blocks(Post $image): void
    {
        $categories = TagCategoriesInfo::is_enabled() ? TagCategories::getKeyedDict() : [];

        $grouped = [];
        foreach ($image->get_tag_array() as $tag) {
            $category = TagCategoriesInfo::is_enabled() ? TagCategories::get_tag_category($tag) : null;
            $grouped[$category ?? ""][] = $tag;
        }

        foreach ($grouped as $category => $tags) {
            $list = UL(["class" => "sidebar-tag-list"]);
            foreach ($tags as $tag) {
                $list->appendChild(LI($this->build_tag($tag, show_underscores: false, show_category: false)));
            }
            if ($category === "") {
                Ctx::$page->add_block(new Block("General", $list, "left", 6, "TagsGeneral"));
            } else {
                // Blocks sharing a position are ordered by header, ie alphabetically
                Ctx::$page->add_block(new Block($categories[$category]['display_multiple'], $list, "left", 5, "Tags_$category"));
            }
        }
    }

    /**
     * @param HTMLElement[] $editor_parts
     * @return HTMLElement[]
     */
    private function filter_editor_parts(array $editor_parts): array
    {
        $kept = [];
        foreach ($editor_parts as $part) {
            $row = $this->get_attr($part, "data-row");
            if (in_array($row, self::HIDDEN_ROWS, true)) {
                continue;
            }
            if (in_array($row, self::EDIT_ONLY_ROWS, true)) {
                $this->add_class($part, "info-edit-only");
            }
            $kept[] = $part;
        }
        return $kept;
    }

    private function get_attr(HTMLElement $el, string $name): ?string
    {
        /** @var array<string, mixed> $attrs */
        $attrs = (new \ReflectionClass($el))->getProperty('attrs')->getValue($el);
        return isset($attrs[$name]) ? (string)$attrs[$name] : null;
    }

    private function add_class(HTMLElement $el, string $class): void
    {
        $prop = (new \ReflectionClass($el))->getProperty('attrs');
        /** @var array<string, mixed> $attrs */
        $attrs = $prop->getValue($el);
        $attrs['class'] = trim(($attrs['class'] ?? '') . " $class");
        $prop->setValue($el, $attrs);
    }
}
