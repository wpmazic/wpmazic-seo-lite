<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

$post_types = get_post_types(
    array(
        'public' => true,
    ),
    'names'
);
unset( $post_types['attachment'] );

$post_types = array_values(
    array_filter(
        $post_types,
        static function ( $post_type ) {
            return post_type_supports( $post_type, 'editor' );
        }
    )
);

if ( empty( $post_types ) ) {
    $post_types = array( 'post', 'page' );
}

$normalize_text = static function ( $value ) {
    $value = trim( preg_replace( '/\s+/', ' ', (string) $value ) );
    if ( '' === $value ) {
        return '';
    }
    if ( function_exists( 'mb_strtolower' ) ) {
        return mb_strtolower( (string) $value );
    }
    return strtolower( (string) $value );
};

$links_table      = wpmazic_seo_get_table_name( 'links' );
$has_links_table  = ( $links_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $links_table ) ) );
$outbound_map     = array();
$inbound_map      = array();
$tracked_links    = 0;
$anchor_distribution = array();
$broken_internal_links = array();
$broken_internal_total = 0;

if ( $has_links_table ) {
    $tracked_links = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$links_table} WHERE type = 'internal'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

    $outbound_rows = $wpdb->get_results(
        "SELECT post_id, COUNT(*) AS total
         FROM {$links_table}
         WHERE type = 'internal'
         GROUP BY post_id", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );
    foreach ( $outbound_rows as $row ) {
        $outbound_map[ (int) $row['post_id'] ] = (int) $row['total'];
    }

    $inbound_rows = $wpdb->get_results(
        "SELECT target_post_id, COUNT(*) AS total
         FROM {$links_table}
         WHERE type = 'internal'
           AND target_post_id > 0
         GROUP BY target_post_id", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );
    foreach ( $inbound_rows as $row ) {
        $inbound_map[ (int) $row['target_post_id'] ] = (int) $row['total'];
    }

    $anchor_rows = $wpdb->get_results(
        "SELECT anchor_text, COUNT(*) AS total
         FROM {$links_table}
         WHERE type = 'internal'
           AND anchor_text <> ''
         GROUP BY anchor_text
         ORDER BY total DESC
         LIMIT 25", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );
    foreach ( $anchor_rows as $anchor_row ) {
        $anchor_text = trim( (string) $anchor_row['anchor_text'] );
        if ( '' === $anchor_text ) {
            continue;
        }
        $anchor_distribution[] = array(
            'anchor' => $anchor_text,
            'count'  => (int) $anchor_row['total'],
        );
    }

    $error_rows = $wpdb->get_results(
        'SELECT url, hits
         FROM ' . wpmazic_seo_get_table_name( '404' ) . '
         ORDER BY hits DESC
         LIMIT 1000', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );
    $error_path_hits = array();
    foreach ( $error_rows as $error_row ) {
        $raw_error = isset( $error_row['url'] ) ? (string) $error_row['url'] : '';
        $err_path  = wp_parse_url( $raw_error, PHP_URL_PATH );
        if ( null === $err_path || false === $err_path || '' === $err_path ) {
            $err_path = $raw_error;
        }
        $err_path = '/' . ltrim( (string) $err_path, '/' );
        $err_path = untrailingslashit( $err_path );
        if ( '' === $err_path ) {
            $err_path = '/';
        }

        $error_path_hits[ $err_path ] = isset( $error_path_hits[ $err_path ] )
            ? $error_path_hits[ $err_path ] + (int) $error_row['hits']
            : (int) $error_row['hits'];
    }

    $link_rows = $wpdb->get_results(
        "SELECT url, anchor_text, COUNT(*) AS uses
         FROM {$links_table}
         WHERE type = 'internal'
         GROUP BY url, anchor_text
         ORDER BY uses DESC
         LIMIT 500", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );

    foreach ( $link_rows as $link_row ) {
        $raw_link = isset( $link_row['url'] ) ? (string) $link_row['url'] : '';
        if ( '' === $raw_link ) {
            continue;
        }

        $path = wp_parse_url( $raw_link, PHP_URL_PATH );
        $path = '/' . ltrim( (string) $path, '/' );
        $path = untrailingslashit( $path );
        if ( '' === $path ) {
            $path = '/';
        }

        if ( ! isset( $error_path_hits[ $path ] ) ) {
            continue;
        }

        $broken_internal_links[] = array(
            'url'      => $raw_link,
            'anchor'   => isset( $link_row['anchor_text'] ) ? (string) $link_row['anchor_text'] : '',
            'uses'     => (int) $link_row['uses'],
            'hits_404' => (int) $error_path_hits[ $path ],
        );
    }

    $broken_internal_total = count( $broken_internal_links );
    $broken_internal_links = array_slice( $broken_internal_links, 0, 20 );
}

$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
$posts_sql         = "
    SELECT
        p.ID,
        p.post_type,
        p.post_title,
        p.post_content,
        p.post_modified,
        MAX(CASE WHEN pm.meta_key = '_wpmazic_title' AND pm.meta_value <> '' THEN pm.meta_value ELSE '' END) AS seo_title,
        MAX(CASE WHEN pm.meta_key = '_wpmazic_description' AND pm.meta_value <> '' THEN pm.meta_value ELSE '' END) AS seo_description,
        MAX(CASE WHEN pm.meta_key = '_wpmazic_keyword' AND pm.meta_value <> '' THEN pm.meta_value ELSE '' END) AS focus_keyword,
        MAX(CASE WHEN pm.meta_key = '_wpmazic_noindex' THEN pm.meta_value ELSE '' END) AS noindex_flag
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} pm
        ON p.ID = pm.post_id
       AND pm.meta_key IN ('_wpmazic_title', '_wpmazic_description', '_wpmazic_keyword', '_wpmazic_noindex')
    WHERE p.post_status = 'publish'
      AND p.post_type IN ({$type_placeholders})
    GROUP BY p.ID
    ORDER BY p.post_modified DESC
";

$posts = $wpdb->get_results( $wpdb->prepare( $posts_sql, $post_types ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$missing_alt_images = (int) $wpdb->get_var(
    "SELECT COUNT(*)
     FROM {$wpdb->posts} p
     LEFT JOIN {$wpdb->postmeta} pm
       ON p.ID = pm.post_id
      AND pm.meta_key = '_wp_attachment_image_alt'
     WHERE p.post_type = 'attachment'
       AND p.post_status = 'inherit'
       AND p.post_mime_type LIKE 'image/%'
       AND (pm.meta_id IS NULL OR pm.meta_value = '')"
);
$cornerstone_count = (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$wpdb->postmeta}
         WHERE meta_key = %s
           AND meta_value = %s",
        '_wpmazic_cornerstone',
        '1'
    )
);

$total_published       = count( $posts );
$noindex_count         = 0;
$indexable_total       = 0;
$with_seo_title        = 0;
$with_seo_description  = 0;
$with_focus_keyword    = 0;
$fully_optimized       = 0;
$orphan_rows           = array();
$priority_rows         = array();
$quality_rows          = array();
$keyword_groups        = array();
$duplicate_title_map   = array();
$duplicate_desc_map    = array();
$thin_content_count    = 0;
$missing_title_count   = 0;
$missing_desc_count    = 0;
$missing_keyword_count = 0;
$internal_link_suggestions = array();

foreach ( $posts as $post_row ) {
    $post_id        = (int) $post_row['ID'];
    $seo_title      = trim( (string) $post_row['seo_title'] );
    $seo_desc       = trim( (string) $post_row['seo_description'] );
    $focus_keyword  = trim( (string) $post_row['focus_keyword'] );
    $noindex_flag   = strtolower( trim( (string) $post_row['noindex_flag'] ) );
    $is_noindex     = in_array( $noindex_flag, array( '1', 'true', 'yes', 'on' ), true );
    $inbound_links  = isset( $inbound_map[ $post_id ] ) ? (int) $inbound_map[ $post_id ] : 0;
    $outbound_links = isset( $outbound_map[ $post_id ] ) ? (int) $outbound_map[ $post_id ] : 0;

    if ( $is_noindex ) {
        $noindex_count++;
    } else {
        $indexable_total++;
    }

    $clean_content = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) $post_row['post_content'] ) ) ) );
    $words         = array_filter( preg_split( '/\s+/', (string) $clean_content ) );
    $word_count    = count( $words );
    $h2_count      = preg_match_all( '/<h2\b/i', (string) $post_row['post_content'] );

    $issues = array();
    $score  = 0;

    $has_title = '' !== $seo_title;
    $has_desc  = '' !== $seo_desc;
    $has_key   = '' !== $focus_keyword;

    if ( ! $is_noindex ) {
        if ( $has_title ) {
            $with_seo_title++;
        } else {
            $missing_title_count++;
            $issues[] = __( 'Missing SEO title', 'wpmazic-seo-lite' );
            $score   += 3;
        }

        if ( $has_desc ) {
            $with_seo_description++;
        } else {
            $missing_desc_count++;
            $issues[] = __( 'Missing meta description', 'wpmazic-seo-lite' );
            $score   += 3;
        }

        if ( $has_key ) {
            $with_focus_keyword++;
        } else {
            $missing_keyword_count++;
            $issues[] = __( 'Missing focus keyword', 'wpmazic-seo-lite' );
            $score   += 2;
        }

        if ( $has_title && $has_desc && $has_key ) {
            $fully_optimized++;
        }

        if ( $word_count < 300 ) {
            $thin_content_count++;
            $issues[] = __( 'Thin content', 'wpmazic-seo-lite' );
            $score   += 2;
        }

        if ( $word_count >= 250 && 0 === (int) $h2_count ) {
            $issues[] = __( 'No H2 headings', 'wpmazic-seo-lite' );
            $score   += 1;
        }

        if ( 0 === $inbound_links ) {
            $orphan_rows[] = array(
                'id'       => $post_id,
                'title'    => $post_row['post_title'],
                'type'     => $post_row['post_type'],
                'modified' => $post_row['post_modified'],
                'outbound' => $outbound_links,
            );
            $issues[] = __( 'Orphan content', 'wpmazic-seo-lite' );
            $score   += 2;
        }

        if ( $word_count >= 400 && $outbound_links < 2 ) {
            $issues[] = __( 'Low internal links out', 'wpmazic-seo-lite' );
            $score   += 1;
        }
    }

    if ( ! empty( $issues ) ) {
        $priority_rows[] = array(
            'id'       => $post_id,
            'title'    => $post_row['post_title'],
            'type'     => $post_row['post_type'],
            'modified' => $post_row['post_modified'],
            'words'    => $word_count,
            'inbound'  => $inbound_links,
            'outbound' => $outbound_links,
            'issues'   => $issues,
            'score'    => $score,
        );
    }

    if ( ! $is_noindex && ( $word_count < 500 || 0 === (int) $h2_count || $outbound_links < 2 ) ) {
        $quality_rows[] = array(
            'id'       => $post_id,
            'title'    => $post_row['post_title'],
            'type'     => $post_row['post_type'],
            'words'    => $word_count,
            'h2'       => (int) $h2_count,
            'outbound' => $outbound_links,
            'modified' => $post_row['post_modified'],
        );
    }

    if ( ! $is_noindex && $has_key ) {
        $normalized_key = $normalize_text( $focus_keyword );
        if ( '' !== $normalized_key ) {
            if ( ! isset( $keyword_groups[ $normalized_key ] ) ) {
                $keyword_groups[ $normalized_key ] = array(
                    'label' => $focus_keyword,
                    'posts' => array(),
                );
            }

            $keyword_groups[ $normalized_key ]['posts'][] = array(
                'id'       => $post_id,
                'title'    => $post_row['post_title'],
                'modified' => $post_row['post_modified'],
            );
        }
    }

    if ( ! $is_noindex && $has_title ) {
        $title_key = $normalize_text( $seo_title );
        if ( '' !== $title_key ) {
            if ( ! isset( $duplicate_title_map[ $title_key ] ) ) {
                $duplicate_title_map[ $title_key ] = array();
            }
            $duplicate_title_map[ $title_key ][] = array(
                'id'    => $post_id,
                'title' => $post_row['post_title'],
            );
        }
    }

    if ( ! $is_noindex && $has_desc ) {
        $desc_key = $normalize_text( $seo_desc );
        if ( '' !== $desc_key ) {
            if ( ! isset( $duplicate_desc_map[ $desc_key ] ) ) {
                $duplicate_desc_map[ $desc_key ] = array();
            }
            $duplicate_desc_map[ $desc_key ][] = array(
                'id'    => $post_id,
                'title' => $post_row['post_title'],
            );
        }
    }
}

// Build internal link suggestions (Yoast/RankMath-style helper).
$existing_link_pairs = array();
if ( $has_links_table ) {
    $pair_rows = $wpdb->get_results(
        "SELECT post_id, target_post_id
         FROM {$links_table}
         WHERE type = 'internal'
           AND target_post_id > 0", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        ARRAY_A
    );

    foreach ( $pair_rows as $pair ) {
        $source_id = (int) $pair['post_id'];
        $target_id = (int) $pair['target_post_id'];
        if ( $source_id > 0 && $target_id > 0 ) {
            $existing_link_pairs[ $source_id . ':' . $target_id ] = true;
        }
    }
}

$content_lookup = array();
$title_lookup   = array();
$type_lookup    = array();
$keyword_lookup = array();
$indexable_ids  = array();

foreach ( $posts as $post_row ) {
    $post_id      = (int) $post_row['ID'];
    $focus_keyword = trim( (string) $post_row['focus_keyword'] );
    $noindex_flag = strtolower( trim( (string) $post_row['noindex_flag'] ) );
    $is_noindex   = in_array( $noindex_flag, array( '1', 'true', 'yes', 'on' ), true );

    $content_lookup[ $post_id ] = $normalize_text( wp_strip_all_tags( strip_shortcodes( (string) $post_row['post_content'] ) ) );
    $title_lookup[ $post_id ]   = (string) $post_row['post_title'];
    $type_lookup[ $post_id ]    = (string) $post_row['post_type'];
    $keyword_lookup[ $post_id ] = $focus_keyword;

    if ( ! $is_noindex ) {
        $indexable_ids[] = $post_id;
    }
}

$targets = array();
foreach ( $indexable_ids as $target_id ) {
    $keyword = trim( (string) $keyword_lookup[ $target_id ] );
    if ( '' === $keyword ) {
        continue;
    }

    $inbound = isset( $inbound_map[ $target_id ] ) ? (int) $inbound_map[ $target_id ] : 0;
    if ( $inbound < 2 ) {
        $targets[] = $target_id;
    }
}

$targets = array_slice( $targets, 0, 20 );
foreach ( $targets as $target_id ) {
    $keyword_norm = (string) $normalize_text( $keyword_lookup[ $target_id ] );
    if ( '' === $keyword_norm ) {
        continue;
    }

    $added_for_target = 0;
    foreach ( $indexable_ids as $source_id ) {
        if ( $source_id === $target_id ) {
            continue;
        }

        if ( isset( $existing_link_pairs[ $source_id . ':' . $target_id ] ) ) {
            continue;
        }

        $source_content = isset( $content_lookup[ $source_id ] ) ? (string) $content_lookup[ $source_id ] : '';
        if ( '' === $source_content || false === strpos( (string) $source_content, (string) $keyword_norm ) ) {
            continue;
        }

        $internal_link_suggestions[] = array(
            'target_id'    => $target_id,
            'target_title' => $title_lookup[ $target_id ],
            'target_type'  => $type_lookup[ $target_id ],
            'keyword'      => $keyword_lookup[ $target_id ],
            'source_id'    => $source_id,
            'source_title' => $title_lookup[ $source_id ],
        );
        $added_for_target++;

        if ( $added_for_target >= 3 || count( $internal_link_suggestions ) >= 50 ) {
            break;
        }
    }

    if ( count( $internal_link_suggestions ) >= 50 ) {
        break;
    }
}

$keyword_conflicts = array_values(
    array_filter(
        $keyword_groups,
        static function ( $group ) {
            return count( $group['posts'] ) > 1;
        }
    )
);

usort(
    $keyword_conflicts,
    static function ( $a, $b ) {
        return count( $b['posts'] ) <=> count( $a['posts'] );
    }
);

$duplicate_titles = array_values(
    array_filter(
        $duplicate_title_map,
        static function ( $posts_group ) {
            return count( $posts_group ) > 1;
        }
    )
);

$duplicate_descriptions = array_values(
    array_filter(
        $duplicate_desc_map,
        static function ( $posts_group ) {
            return count( $posts_group ) > 1;
        }
    )
);

usort(
    $priority_rows,
    static function ( $a, $b ) {
        if ( $b['score'] === $a['score'] ) {
            return strcmp( $b['modified'], $a['modified'] );
        }
        return $b['score'] <=> $a['score'];
    }
);

usort(
    $orphan_rows,
    static function ( $a, $b ) {
        return strcmp( $b['modified'], $a['modified'] );
    }
);

usort(
    $quality_rows,
    static function ( $a, $b ) {
        if ( $a['words'] === $b['words'] ) {
            return strcmp( $b['modified'], $a['modified'] );
        }
        return $a['words'] <=> $b['words'];
    }
);

$orphan_total_count      = count( $orphan_rows );
$keyword_conflict_total  = count( $keyword_conflicts );
$suggestion_total_count  = count( $internal_link_suggestions );

$priority_rows          = array_slice( $priority_rows, 0, 25 );
$orphan_rows            = array_slice( $orphan_rows, 0, 20 );
$quality_rows           = array_slice( $quality_rows, 0, 20 );
$keyword_conflicts      = array_slice( $keyword_conflicts, 0, 15 );
$duplicate_titles       = array_slice( $duplicate_titles, 0, 10 );
$duplicate_descriptions = array_slice( $duplicate_descriptions, 0, 10 );
$internal_link_suggestions = array_slice( $internal_link_suggestions, 0, 30 );

$title_coverage    = $indexable_total > 0 ? round( ( $with_seo_title / $indexable_total ) * 100, 1 ) : 0;
$desc_coverage     = $indexable_total > 0 ? round( ( $with_seo_description / $indexable_total ) * 100, 1 ) : 0;
$keyword_coverage  = $indexable_total > 0 ? round( ( $with_focus_keyword / $indexable_total ) * 100, 1 ) : 0;
$full_coverage     = $indexable_total > 0 ? round( ( $fully_optimized / $indexable_total ) * 100, 1 ) : 0;
$issues_weight     = $missing_title_count + $missing_desc_count + $thin_content_count + $orphan_total_count;
$health_score      = $indexable_total > 0 ? max( 0, 100 - (int) round( ( $issues_weight / max( 1, $indexable_total * 3 ) ) * 100 ) ) : 0;

wpmazic_seo_admin_shell_open(
    __( 'SEO Analysis', 'wpmazic-seo-lite' ),
    __( 'Deep SEO audit for metadata quality, internal linking, cannibalization, and content depth.', 'wpmazic-seo-lite' )
);
?>

<div class="wmz-grid">
    <?php
    $summary_cards = array(
        __( 'SEO Health Score', 'wpmazic-seo-lite' )              => $health_score . '/100',
        __( 'Indexable URLs', 'wpmazic-seo-lite' )                => $indexable_total,
        __( 'Noindex URLs', 'wpmazic-seo-lite' )                  => $noindex_count,
        __( 'Title Coverage', 'wpmazic-seo-lite' )                => $title_coverage . '%',
        __( 'Description Coverage', 'wpmazic-seo-lite' )          => $desc_coverage . '%',
        __( 'Keyword Coverage', 'wpmazic-seo-lite' )              => $keyword_coverage . '%',
        __( 'Fully Optimized', 'wpmazic-seo-lite' )               => $full_coverage . '%',
        __( 'Internal Links Tracked', 'wpmazic-seo-lite' )        => $tracked_links,
        __( 'Anchor Text Variants', 'wpmazic-seo-lite' )          => count( $anchor_distribution ),
        __( 'Link Suggestions', 'wpmazic-seo-lite' )              => $suggestion_total_count,
        __( 'Broken Internal Links', 'wpmazic-seo-lite' )         => $broken_internal_total,
        __( 'Missing Image ALT', 'wpmazic-seo-lite' )             => $missing_alt_images,
        __( 'Pillar/Cornerstone Pages', 'wpmazic-seo-lite' )      => $cornerstone_count,
        __( 'Keyword Cannibalization Sets', 'wpmazic-seo-lite' )  => $keyword_conflict_total,
    );
    foreach ( $summary_cards as $label => $value ) :
        ?>
        <div class="wmz-stat">
            <p class="wmz-stat-label"><?php echo esc_html( $label ); ?></p>
            <p class="wmz-stat-value"><?php echo esc_html( (string) $value ); ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e( 'Priority Fix Queue', 'wpmazic-seo-lite' ); ?></h2>
    <p class="wmz-subtle">
        <?php esc_html_e( 'Start with the highest score first. These pages usually give the fastest ranking improvements after fixing.', 'wpmazic-seo-lite' ); ?>
    </p>
    <div class="wmz-actions">
        <a class="button button-primary" href="<?php echo esc_url( wpmazic_seo_admin_page_url( 'bulk-editor' ) ); ?>"><?php esc_html_e( 'Open Bulk Editor', 'wpmazic-seo-lite' ); ?></a>
        <a class="button" href="<?php echo esc_url( wpmazic_seo_admin_page_url( 'tools' ) ); ?>"><?php esc_html_e( 'Run SEO Tools', 'wpmazic-seo-lite' ); ?></a>
    </div>
    <div class="wmz-table-wrap">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Page', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Issues', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Words', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'In Links', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Out Links', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Score', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wpmazic-seo-lite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $priority_rows ) ) : ?>
                    <?php foreach ( $priority_rows as $row ) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $row['title'] ? $row['title'] : __( '(no title)', 'wpmazic-seo-lite' ) ); ?></strong><br>
                                <span class="wmz-subtle"><?php echo esc_html( strtoupper( (string) $row['type'] ) ); ?></span>
                            </td>
                            <td><?php echo esc_html( implode( ', ', $row['issues'] ) ); ?></td>
                            <td><?php echo esc_html( (string) $row['words'] ); ?></td>
                            <td><?php echo esc_html( (string) $row['inbound'] ); ?></td>
                            <td><?php echo esc_html( (string) $row['outbound'] ); ?></td>
                            <td><span class="wmz-pill"><?php echo esc_html( (string) $row['score'] ); ?></span></td>
                            <td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php esc_html_e( 'Optimize', 'wpmazic-seo-lite' ); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No high-priority SEO issues detected.', 'wpmazic-seo-lite' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e( 'Internal Link Suggestions', 'wpmazic-seo-lite' ); ?></h2>
    <p class="wmz-subtle"><?php esc_html_e( 'Suggested source pages that mention your target keyword but are not linking to the target page yet.', 'wpmazic-seo-lite' ); ?></p>
    <div class="wmz-table-wrap">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Target Page', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Keyword', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Suggested Source', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wpmazic-seo-lite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $internal_link_suggestions ) ) : ?>
                    <?php foreach ( $internal_link_suggestions as $item ) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( get_edit_post_link( $item['target_id'] ) ); ?>"><?php echo esc_html( $item['target_title'] ? $item['target_title'] : __( '(no title)', 'wpmazic-seo-lite' ) ); ?></a><br>
                                <span class="wmz-subtle"><?php echo esc_html( strtoupper( (string) $item['target_type'] ) ); ?></span>
                            </td>
                            <td><?php echo esc_html( (string) $item['keyword'] ); ?></td>
                            <td><a href="<?php echo esc_url( get_edit_post_link( $item['source_id'] ) ); ?>"><?php echo esc_html( $item['source_title'] ? $item['source_title'] : __( '(no title)', 'wpmazic-seo-lite' ) ); ?></a></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $item['source_id'] ) ); ?>"><?php esc_html_e( 'Add Link', 'wpmazic-seo-lite' ); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="4"><?php esc_html_e( 'No actionable internal-link suggestions found right now.', 'wpmazic-seo-lite' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Anchor Text Distribution', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Review top used anchor texts to avoid over-optimization and improve anchor diversity.', 'wpmazic-seo-lite' ); ?></p>
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Anchor Text', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Uses', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $anchor_distribution ) ) : ?>
                        <?php foreach ( $anchor_distribution as $anchor_row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $anchor_row['anchor'] ); ?></td>
                                <td><?php echo esc_html( (string) $anchor_row['count'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'No anchor text data available yet.', 'wpmazic-seo-lite' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Potential Broken Internal Links', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Detected from internal link index matched against 404 logs. Confirm and fix high-impact URLs first.', 'wpmazic-seo-lite' ); ?></p>
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'URL', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Anchor', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Link Uses', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( '404 Hits', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $broken_internal_links ) ) : ?>
                        <?php foreach ( $broken_internal_links as $broken_row ) : ?>
                            <tr>
                                <td><a href="<?php echo esc_url( $broken_row['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $broken_row['url'] ); ?></a></td>
                                <td><?php echo esc_html( '' !== trim( (string) $broken_row['anchor'] ) ? $broken_row['anchor'] : __( '(empty)', 'wpmazic-seo-lite' ) ); ?></td>
                                <td><?php echo esc_html( (string) $broken_row['uses'] ); ?></td>
                                <td><?php echo esc_html( (string) $broken_row['hits_404'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No potential broken internal links detected from current logs.', 'wpmazic-seo-lite' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Keyword Cannibalization', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'Multiple pages targeting the same keyword can split ranking signals.', 'wpmazic-seo-lite' ); ?></p>
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Keyword', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Competing Pages', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $keyword_conflicts ) ) : ?>
                        <?php foreach ( $keyword_conflicts as $group ) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html( $group['label'] ); ?></strong><br>
                                    <span class="wmz-subtle"><?php echo esc_html( sprintf( __( '%d URLs', 'wpmazic-seo-lite' ), count( $group['posts'] ) ) ); ?></span>
                                </td>
                                <td>
                                    <?php foreach ( $group['posts'] as $item ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'No keyword cannibalization groups detected.', 'wpmazic-seo-lite' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Orphan Content', 'wpmazic-seo-lite' ); ?></h2>
        <p class="wmz-subtle"><?php esc_html_e( 'These pages currently have zero inbound internal links.', 'wpmazic-seo-lite' ); ?></p>
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Page', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Out Links', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Action', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $orphan_rows ) ) : ?>
                        <?php foreach ( $orphan_rows as $row ) : ?>
                            <tr>
                                <td><?php echo esc_html( $row['title'] ? $row['title'] : __( '(no title)', 'wpmazic-seo-lite' ) ); ?></td>
                                <td><?php echo esc_html( strtoupper( (string) $row['type'] ) ); ?></td>
                                <td><?php echo esc_html( (string) $row['outbound'] ); ?></td>
                                <td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php esc_html_e( 'Add Links', 'wpmazic-seo-lite' ); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="4"><?php esc_html_e( 'No orphan content detected from tracked links.', 'wpmazic-seo-lite' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="tw-grid md:tw-grid-cols-2 tw-gap-4">
    <div class="wmz-card">
        <h2><?php esc_html_e( 'Duplicate SEO Titles', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Duplicate Set', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Pages', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $duplicate_titles ) ) : ?>
                        <?php foreach ( $duplicate_titles as $group ) : ?>
                            <tr>
                                <td><?php echo esc_html( sprintf( __( '%d pages', 'wpmazic-seo-lite' ), count( $group ) ) ); ?></td>
                                <td>
                                    <?php foreach ( $group as $item ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'No duplicate custom SEO titles detected.', 'wpmazic-seo-lite' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="wmz-card">
        <h2><?php esc_html_e( 'Duplicate Meta Descriptions', 'wpmazic-seo-lite' ); ?></h2>
        <div class="wmz-table-wrap">
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Duplicate Set', 'wpmazic-seo-lite' ); ?></th>
                        <th><?php esc_html_e( 'Pages', 'wpmazic-seo-lite' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $duplicate_descriptions ) ) : ?>
                        <?php foreach ( $duplicate_descriptions as $group ) : ?>
                            <tr>
                                <td><?php echo esc_html( sprintf( __( '%d pages', 'wpmazic-seo-lite' ), count( $group ) ) ); ?></td>
                                <td>
                                    <?php foreach ( $group as $item ) : ?>
                                        <a href="<?php echo esc_url( get_edit_post_link( $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><br>
                                    <?php endforeach; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="2"><?php esc_html_e( 'No duplicate custom descriptions detected.', 'wpmazic-seo-lite' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="wmz-card">
    <h2><?php esc_html_e( 'Content Quality Watchlist', 'wpmazic-seo-lite' ); ?></h2>
    <p class="wmz-subtle"><?php esc_html_e( 'Pages with low depth, weak heading structure, or weak internal linking.', 'wpmazic-seo-lite' ); ?></p>
    <div class="wmz-table-wrap">
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Page', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Words', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'H2 Count', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Out Links', 'wpmazic-seo-lite' ); ?></th>
                    <th><?php esc_html_e( 'Action', 'wpmazic-seo-lite' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $quality_rows ) ) : ?>
                    <?php foreach ( $quality_rows as $row ) : ?>
                        <tr>
                            <td><?php echo esc_html( $row['title'] ? $row['title'] : __( '(no title)', 'wpmazic-seo-lite' ) ); ?></td>
                            <td><?php echo esc_html( (string) $row['words'] ); ?></td>
                            <td><?php echo esc_html( (string) $row['h2'] ); ?></td>
                            <td><?php echo esc_html( (string) $row['outbound'] ); ?></td>
                            <td><a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>"><?php esc_html_e( 'Improve', 'wpmazic-seo-lite' ); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr><td colspan="5"><?php esc_html_e( 'No content quality risks detected in current scan.', 'wpmazic-seo-lite' ); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php wpmazic_seo_admin_shell_close(); ?>
