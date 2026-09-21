<?php

declare(strict_types=1);

namespace Shimmie2;

use function MicroHTML\joinHTML;

class Danbooru2CustomTagListTheme extends TagListTheme
{
    /**
     * The post's own tags are rendered per-category in the sidebar by
     * Danbooru2CustomViewPostTheme, so only "Related Tags" is shown here -
     * as a comma separated line underneath the post.
     *
     * @param array<array{tag: tag-string, count: int}> $tag_infos
     */
    public function display_related_block(array $tag_infos, string $block_name): void
    {
        if ($block_name !== "Related Tags") {
            return;
        }

        if (Ctx::$config->get(TagListConfig::RELATED_SORT) === TagListConfig::SORT_ALPHABETICAL) {
            usort($tag_infos, fn ($a, $b) => strcasecmp($a['tag'], $b['tag']));
        }

        $tags = [];
        foreach ($tag_infos as $row) {
            $tags[] = $this->build_tag($row['tag'], show_underscores: false, show_category: false);
        }

        Ctx::$page->add_block(new Block("Related Tags", joinHTML(", ", $tags), "main", 20, "RelatedTags"));
    }

    /**
     * @param array<array{tag: tag-string, count: int}> $tag_infos
     */
    public function display_split_related_block(array $tag_infos): void
    {
        // Handled by Danbooru2CustomViewPostTheme::add_tag_blocks
    }
}
