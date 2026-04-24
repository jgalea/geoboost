<?php
namespace GeoBoost\Admin;

final class FixPage {

	private const SLUG     = 'geoboost-fix';
	private const PER_PAGE = 25;

	private const SIGNALS = array(
		// Essentials
		'missing_article_schema' => 'has_article_schema',
		'heading_hierarchy'      => 'heading_hierarchy_ok',
		'word_count'             => 'word_count_ok',
		'missing_author_eeat'    => 'has_author_eeat',
		// Recommendations
		'missing_summary_block'  => 'has_summary_block',
		'missing_excerpt'        => 'has_excerpt',
		'missing_key_facts'      => 'has_key_facts',
		'missing_faq'            => 'has_faq_signal',
		'missing_featured'       => 'has_featured',
		'missing_outbound_link'  => 'has_outbound_link',
		'missing_internal_link'  => 'has_internal_link',
	);

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_geoboost_apply_summary', array( $this, 'handle_apply_summary' ) );
		add_action( 'admin_post_geoboost_dismiss', array( $this, 'handle_dismiss' ) );
		add_action( 'admin_post_geoboost_restore', array( $this, 'handle_restore' ) );
	}

	public function add_menu(): void {
		// Hidden page — accessible via URL, not shown in the admin menu.
		add_submenu_page(
			'',
			__( 'Fix', 'geoboost' ),
			__( 'Fix', 'geoboost' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'admin_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style(
			'geoboost-admin',
			GEOBOOST_URL . 'assets/admin/admin.css',
			array(),
			GEOBOOST_VERSION
		);
		wp_enqueue_style(
			'geoboost-fix',
			GEOBOOST_URL . 'assets/admin/fix.css',
			array( 'geoboost-admin' ),
			GEOBOOST_VERSION
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
		if ( ! isset( self::SIGNALS[ $type ] ) ) {
			$this->render_index();
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$view = isset( $_GET['view'] ) && 'dismissed' === $_GET['view'] ? 'dismissed' : 'pending';
		$this->render_type( $type, $paged, $view );
	}

	private function render_index(): void {
		$meta = $this->meta();
		echo '<div class="wrap geoboost-admin">';
		printf( '<div class="geoboost-header"><h1>%s</h1></div>', esc_html__( 'Fix recommendations', 'geoboost' ) );
		echo '<p class="geoboost-fix__intro">' . esc_html__( 'Pick a recommendation to see affected posts and quick fixes.', 'geoboost' ) . '</p>';
		echo '<div class="geoboost-fix__index">';
		foreach ( $meta as $type => $info ) {
			printf(
				'<a class="geoboost-fix__index-card" href="%s"><h3>%s</h3><p>%s</p><span class="geoboost-fix__index-arrow" aria-hidden="true">→</span></a>',
				esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&type=' . rawurlencode( $type ) ) ),
				esc_html( $info['title'] ),
				esc_html( $info['summary'] )
			);
		}
		echo '</div>';
		echo '</div>';
	}

	private function render_type( string $type, int $paged, string $view ): void {
		$meta          = $this->meta()[ $type ];
		$dismissals    = new Dismissals();
		$pending_ids   = $this->matching_post_ids( $type, false );
		$dismissed_ids = $this->matching_post_ids( $type, true );
		$ids           = 'dismissed' === $view ? $dismissed_ids : $pending_ids;
		$total         = count( $ids );
		$offset        = ( $paged - 1 ) * self::PER_PAGE;
		$page_ids      = array_slice( $ids, $offset, self::PER_PAGE );

		echo '<div class="wrap geoboost-admin">';

		echo '<div class="geoboost-header">';
		printf( '<h1>%s</h1>', esc_html( $meta['title'] ) );
		printf(
			'<span class="geoboost-header__meta"><a href="%s">%s</a></span>',
			esc_url( admin_url( 'admin.php?page=geoboost' ) ),
			esc_html__( '← Back to dashboard', 'geoboost' )
		);
		echo '</div>';

		$this->maybe_render_notice();

		echo '<div class="geoboost-fix__layout">';

		echo '<div class="geoboost-card geoboost-fix__explain">';
		printf( '<h2>%s</h2>', esc_html__( 'Why this matters', 'geoboost' ) );
		printf( '<p>%s</p>', esc_html( $meta['why'] ) );
		printf( '<h2>%s</h2>', esc_html__( 'How to fix', 'geoboost' ) );
		printf( '<p>%s</p>', esc_html( $meta['how'] ) );
		if ( ! empty( $meta['cta_url'] ) ) {
			printf(
				'<p><a class="button button-primary" href="%s">%s</a></p>',
				esc_url( $meta['cta_url'] ),
				esc_html( $meta['cta_label'] )
			);
		}
		echo '</div>';

		echo '<div class="geoboost-card geoboost-fix__posts">';

		$this->render_view_tabs( $type, $view, count( $pending_ids ), count( $dismissed_ids ) );

		if ( empty( $page_ids ) ) {
			$empty = 'dismissed' === $view
				? __( 'You haven\'t dismissed any posts for this recommendation.', 'geoboost' )
				: __( 'Nothing to fix here. Great work.', 'geoboost' );
			echo '<p class="geoboost-empty">' . esc_html( $empty ) . '</p>';
			echo '</div></div></div>';
			return;
		}

		echo '<table class="geoboost-table geoboost-fix__table"><thead><tr>';
		echo '<th>' . esc_html__( 'Entry', 'geoboost' ) . '</th>';
		echo '<th class="geoboost-fix__th-type">' . esc_html__( 'Type', 'geoboost' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'geoboost' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $page_ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$edit      = (string) get_edit_post_link( $post->ID );
			$permalink = (string) get_permalink( $post->ID );

			$type_label = 'page' === $post->post_type ? __( 'Page', 'geoboost' ) : __( 'Post', 'geoboost' );

			echo '<tr>';
			printf(
				'<td class="geoboost-table__title"><a href="%s">%s</a><div class="geoboost-fix__preview">%s</div></td>',
				esc_url( $edit ),
				esc_html( $post->post_title ),
				esc_html( $this->preview( $post ) )
			);
			printf(
				'<td class="geoboost-fix__td-type"><span class="geoboost-fix__type geoboost-fix__type--%s">%s</span></td>',
				esc_attr( $post->post_type ),
				esc_html( $type_label )
			);
			echo '<td class="geoboost-fix__actions">';
			if ( 'dismissed' === $view ) {
				$this->render_restore_button( $type, $post->ID );
			} else {
				$this->render_actions( $type, $post );
				$this->render_dismiss_button( $type, $post->ID );
			}
			printf(
				'<a class="geoboost-fix__action-secondary" href="%s" target="_blank" rel="noopener"><span class="dashicons dashicons-external"></span>%s</a>',
				esc_url( $permalink ),
				esc_html__( 'Preview', 'geoboost' )
			);
			echo '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		$this->render_pagination( $type, $paged, $total, $view );
		echo '</div>'; // .geoboost-fix__posts

		echo '</div>'; // .geoboost-fix__layout
		echo '</div>'; // .wrap
	}

	private function render_actions( string $type, \WP_Post $post ): void {
		$edit = (string) get_edit_post_link( $post->ID );
		printf(
			'<a class="button button-primary" href="%s">%s</a>',
			esc_url( $edit ),
			esc_html__( 'Open in editor', 'geoboost' )
		);

		if ( 'missing_summary_block' === $type ) {
			$suggested = $this->suggested_summary( $post );
			if ( '' !== $suggested ) {
				$action_url = wp_nonce_url(
					admin_url( 'admin-post.php?action=geoboost_apply_summary&post=' . $post->ID ),
					'geoboost_apply_summary_' . $post->ID
				);
				printf(
					'<form method="post" action="%s" class="geoboost-fix__form"><input type="submit" class="button" value="%s" title="%s"></form>',
					esc_url( $action_url ),
					esc_attr__( 'Apply suggested summary', 'geoboost' ),
					esc_attr( $this->truncate( $suggested, 160 ) )
				);
			}
		}
	}

	private function render_view_tabs( string $type, string $view, int $pending, int $dismissed ): void {
		$base = admin_url( 'admin.php?page=' . self::SLUG . '&type=' . rawurlencode( $type ) );
		echo '<div class="geoboost-fix__tabs">';
		printf(
			'<a class="geoboost-fix__tab%s" href="%s">%s <span class="geoboost-fix__tab-count">%d</span></a>',
			'pending' === $view ? ' is-active' : '',
			esc_url( $base ),
			esc_html__( 'Pending', 'geoboost' ),
			(int) $pending
		);
		if ( $dismissed > 0 ) {
			printf(
				'<a class="geoboost-fix__tab%s" href="%s">%s <span class="geoboost-fix__tab-count">%d</span></a>',
				'dismissed' === $view ? ' is-active' : '',
				esc_url( $base . '&view=dismissed' ),
				esc_html__( 'Dismissed', 'geoboost' ),
				(int) $dismissed
			);
		}
		echo '</div>';
	}

	private function render_dismiss_button( string $type, int $post_id ): void {
		$url = wp_nonce_url(
			admin_url(
				'admin-post.php?action=geoboost_dismiss&type=' . rawurlencode( $type ) . '&post=' . $post_id
			),
			'geoboost_dismiss_' . $type . '_' . $post_id
		);
		printf(
			'<form method="post" action="%s" class="geoboost-fix__form"><input type="submit" class="button-link geoboost-fix__dismiss" value="%s" title="%s"></form>',
			esc_url( $url ),
			esc_attr__( 'Dismiss', 'geoboost' ),
			esc_attr__( 'Hide this post from this recommendation', 'geoboost' )
		);
	}

	private function render_restore_button( string $type, int $post_id ): void {
		$url = wp_nonce_url(
			admin_url(
				'admin-post.php?action=geoboost_restore&type=' . rawurlencode( $type ) . '&post=' . $post_id
			),
			'geoboost_restore_' . $type . '_' . $post_id
		);
		printf(
			'<form method="post" action="%s" class="geoboost-fix__form"><input type="submit" class="button" value="%s"></form>',
			esc_url( $url ),
			esc_attr__( 'Restore', 'geoboost' )
		);
	}

	public function handle_dismiss(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'geoboost' ), 403 );
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
		if ( ! isset( self::SIGNALS[ $type ] ) || $post_id <= 0 ) {
			wp_die( esc_html__( 'Invalid request.', 'geoboost' ), 400 );
		}
		check_admin_referer( 'geoboost_dismiss_' . $type . '_' . $post_id );
		( new Dismissals() )->dismiss( $post_id, $type );
		$this->redirect_with_notice( $type, 'dismissed', $post_id );
	}

	public function handle_restore(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'geoboost' ), 403 );
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$type    = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( (string) $_GET['type'] ) ) : '';
		if ( ! isset( self::SIGNALS[ $type ] ) || $post_id <= 0 ) {
			wp_die( esc_html__( 'Invalid request.', 'geoboost' ), 400 );
		}
		check_admin_referer( 'geoboost_restore_' . $type . '_' . $post_id );
		( new Dismissals() )->restore( $post_id, $type );
		$this->redirect_with_notice( $type, 'restored', $post_id, 'dismissed' );
	}

	public function handle_apply_summary(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'geoboost' ), 403 );
		}
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		check_admin_referer( 'geoboost_apply_summary_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			wp_die( esc_html__( 'Invalid post.', 'geoboost' ), 400 );
		}

		$text = $this->suggested_summary( $post );
		if ( '' === $text ) {
			$this->redirect_with_notice( 'missing_summary_block', 'no_text', $post_id );
			return;
		}

		$block = $this->serialize_summary_block( $text );
		$new   = $block . "\n\n" . $post->post_content;
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new,
			)
		);

		$this->redirect_with_notice( 'missing_summary_block', 'applied', $post_id );
	}

	private function serialize_summary_block( string $paragraph ): string {
		$attrs = wp_json_encode(
			array(
				'style'     => 'paragraph',
				'paragraph' => $paragraph,
			)
		);
		return '<!-- wp:geoboost/summary ' . $attrs . ' /-->';
	}

	private function suggested_summary( \WP_Post $post ): string {
		$excerpt = trim( (string) $post->post_excerpt );
		if ( '' !== $excerpt ) {
			return $this->truncate( $excerpt, 280 );
		}
		$stripped = wp_strip_all_tags( (string) $post->post_content, true );
		$stripped = trim( preg_replace( '/\s+/u', ' ', $stripped ) );
		if ( '' === $stripped ) {
			return '';
		}
		if ( preg_match( '/^(.+?[.!?])(\s|$)/u', $stripped, $m ) ) {
			return $this->truncate( $m[1], 280 );
		}
		return $this->truncate( $stripped, 280 );
	}

	private function preview( \WP_Post $post ): string {
		$text = trim( (string) $post->post_excerpt );
		if ( '' === $text ) {
			$text = wp_strip_all_tags( (string) $post->post_content, true );
		}
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
		return $this->truncate( $text, 140 );
	}

	private function truncate( string $text, int $max ): string {
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}
		return rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…';
	}

	private function render_pagination( string $type, int $paged, int $total, string $view = 'pending' ): void {
		$pages = (int) ceil( $total / self::PER_PAGE );
		if ( $pages <= 1 ) {
			return;
		}
		$base = admin_url( 'admin.php?page=' . self::SLUG . '&type=' . rawurlencode( $type ) );
		if ( 'dismissed' === $view ) {
			$base .= '&view=dismissed';
		}
		echo '<nav class="geoboost-fix__pagination" aria-label="' . esc_attr__( 'Posts pagination', 'geoboost' ) . '">';
		if ( $paged > 1 ) {
			printf(
				'<a class="button" href="%s">%s</a>',
				esc_url( $base . '&paged=' . ( $paged - 1 ) ),
				esc_html__( '← Previous', 'geoboost' )
			);
		}
		printf(
			'<span class="geoboost-fix__pagination-info">%s</span>',
			esc_html(
				sprintf(
					/* translators: 1: current page, 2: total pages */
					__( 'Page %1$d of %2$d', 'geoboost' ),
					$paged,
					$pages
				)
			)
		);
		if ( $paged < $pages ) {
			printf(
				'<a class="button" href="%s">%s</a>',
				esc_url( $base . '&paged=' . ( $paged + 1 ) ),
				esc_html__( 'Next →', 'geoboost' )
			);
		}
		echo '</nav>';
	}

	private function redirect_with_notice( string $type, string $notice, int $post_id, string $view = '' ): void {
		$args = array(
			'page'   => self::SLUG,
			'type'   => $type,
			'notice' => $notice,
			'post'   => $post_id,
		);
		if ( '' !== $view ) {
			$args['view'] = $view;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function maybe_render_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['notice'] ) ) : '';
		if ( '' === $notice ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post_id  = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
		$messages = array(
			'applied'   => __( 'Summary block added. Open the post to review the text — status updates on save.', 'geoboost' ),
			'no_text'   => __( 'This post has no excerpt and no visible text. Add an excerpt first, then re-try.', 'geoboost' ),
			'dismissed' => __( 'Post dismissed. It won\'t appear in this recommendation until you restore it.', 'geoboost' ),
			'restored'  => __( 'Post restored. It is back in the pending list.', 'geoboost' ),
		);
		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}
		$tone = 'no_text' === $notice ? 'notice-warning' : 'notice-success';
		$edit = $post_id > 0 ? (string) get_edit_post_link( $post_id ) : '';
		printf(
			'<div class="notice %s is-dismissible"><p>%s %s</p></div>',
			esc_attr( $tone ),
			esc_html( $messages[ $notice ] ),
			'' !== $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html__( 'Open the post →', 'geoboost' ) . '</a>' : ''
		);
	}

	/**
	 * @return array<int,int>
	 */
	private function matching_post_ids( string $type, bool $dismissed_only = false ): array {
		global $wpdb;
		$signal = self::SIGNALS[ $type ];
		$rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT post_id, meta_value AS signals FROM {$wpdb->postmeta} WHERE meta_key = '_geoboost_signals'"
		);
		$ids    = array();
		foreach ( (array) $rows as $row ) {
			$d = maybe_unserialize( $row->signals );
			if ( ! is_array( $d ) ) {
				continue;
			}
			if ( empty( $d[ $signal ] ) ) {
				$ids[] = (int) $row->post_id;
			}
		}
		if ( empty( $ids ) ) {
			return array();
		}

		$dismissed = ( new Dismissals() )->dismissed_ids_for_type( $type );
		$ids       = $dismissed_only
			? array_values( array_intersect( $ids, $dismissed ) )
			: array_values( array_diff( $ids, $dismissed ) );

		if ( empty( $ids ) ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
		$published = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE ID IN ($placeholders) AND post_status = 'publish' AND post_type IN ('post','page') ORDER BY post_date DESC",
				$ids
			)
		);
		// phpcs:enable

		return array_map( 'intval', (array) $published );
	}

	/**
	 * @return array<string,array<string,string>>
	 */
	private function meta(): array {
		return array(
			// Essentials
			'missing_article_schema' => array(
				'title'   => __( 'Missing Article schema', 'geoboost' ),
				'summary' => __( 'No SEO plugin or GEOBoost output is emitting Article schema.', 'geoboost' ),
				'why'     => __( 'Article schema tells AI engines what kind of content this is, who wrote it, and when. Without it, extraction is guesswork.', 'geoboost' ),
				'how'     => __( 'Either enable GEOBoost\'s schema output for posts, or install/configure an SEO plugin (Yoast, RankMath, SEOPress, TSF) that emits Article schema.', 'geoboost' ),
			),
			'heading_hierarchy'      => array(
				'title'   => __( 'Heading hierarchy issues', 'geoboost' ),
				'summary' => __( 'Wonky heading levels confuse extractors.', 'geoboost' ),
				'why'     => __( 'A clean h1 → h2 → h3 outline helps AI engines understand your document structure. Skipped levels make extraction unreliable.', 'geoboost' ),
				'how'     => __( 'In the editor, use Heading 2 for sections, Heading 3 for subsections. Avoid jumping from H2 straight to H4.', 'geoboost' ),
			),
			'word_count'             => array(
				'title'   => __( 'Content under 150 words', 'geoboost' ),
				'summary' => __( 'Very thin posts lack depth for AI to confidently cite.', 'geoboost' ),
				'why'     => __( 'AI engines need enough context to extract a quote with confidence. Under ~150 words there\'s rarely enough.', 'geoboost' ),
				'how'     => __( 'Expand with examples, data, or a Key Facts block. If the topic is genuinely small, consider merging with a related post.', 'geoboost' ),
			),
			'missing_author_eeat'    => array(
				'title'     => __( 'Weak author E-E-A-T signals', 'geoboost' ),
				'summary'   => __( 'AI engines reward content with credentialed authors.', 'geoboost' ),
				'why'       => __( 'Expertise, Experience, Authoritativeness, Trust (E-E-A-T) signals on the author profile feed Person schema, giving AI engines a reason to trust your byline.', 'geoboost' ),
				'how'       => __( 'Complete the GEOBoost fields on each author profile: credentials and areas of expertise.', 'geoboost' ),
				'cta_url'   => admin_url( 'users.php' ),
				'cta_label' => __( 'Go to Users', 'geoboost' ),
			),
			// Recommendations
			'missing_summary_block'  => array(
				'title'   => __( 'Missing Summary block', 'geoboost' ),
				'summary' => __( 'Add a TL;DR summary so AI engines can cite your post in one sentence.', 'geoboost' ),
				'why'     => __( 'A compact in-body Summary gives language models a ready-made answer to quote. Without one, they synthesize their own — which is slower, less accurate, and less likely to name your site as the source.', 'geoboost' ),
				'how'     => __( 'Add the GEOBoost Summary block near the top of each post with a 1–3 sentence distillation of what the post is about. For posts with an excerpt, GEOBoost can apply a suggested summary below in one click; you can refine it later.', 'geoboost' ),
			),
			'missing_excerpt'        => array(
				'title'   => __( 'Missing post excerpt', 'geoboost' ),
				'summary' => __( 'The excerpt feeds Article schema description and llms.txt.', 'geoboost' ),
				'why'     => __( 'WordPress falls back to an auto-trim of the post content when the excerpt is empty, but an explicit author-written excerpt is sharper and more accurate for AI consumption.', 'geoboost' ),
				'how'     => __( 'In the editor sidebar, expand "Excerpt" and write 1–3 sentences summarizing the post.', 'geoboost' ),
			),
			'missing_key_facts'      => array(
				'title'   => __( 'Missing Key Facts', 'geoboost' ),
				'summary' => __( 'List 3–5 essential facts LLMs can lift out verbatim.', 'geoboost' ),
				'why'     => __( 'AI engines prefer structured, bullet-list facts over prose. Key Facts make your post easier to extract and cite with high confidence.', 'geoboost' ),
				'how'     => __( 'Open the editor and insert the GEOBoost Key Facts block. List 3–5 concrete, stand-alone facts.', 'geoboost' ),
			),
			'missing_faq'            => array(
				'title'   => __( 'Missing FAQ signals', 'geoboost' ),
				'summary' => __( 'FAQs map directly to how people ask AI questions.', 'geoboost' ),
				'why'     => __( 'FAQs align with question-shaped queries AI engines receive. The FAQPage schema tells models that these Q/A pairs are authoritative.', 'geoboost' ),
				'how'     => __( 'Use the GEOBoost FAQ block for 3–5 questions a real person would ask about the topic.', 'geoboost' ),
			),
			'missing_featured'       => array(
				'title'     => __( 'No featured image', 'geoboost' ),
				'summary'   => __( 'Featured images feed Open Graph, Twitter, and Article schema.', 'geoboost' ),
				'why'       => __( 'A featured image is used by social previews, Article schema, and some AI surfaces.', 'geoboost' ),
				'how'       => __( 'Open each post and set a featured image.', 'geoboost' ),
				'cta_url'   => admin_url( 'upload.php' ),
				'cta_label' => __( 'Media library', 'geoboost' ),
			),
			'missing_outbound_link'  => array(
				'title'   => __( 'No outbound citations', 'geoboost' ),
				'summary' => __( 'Citing external authorities boosts credibility for AI extraction.', 'geoboost' ),
				'why'     => __( 'Articles that reference primary sources or known authorities are easier for AI engines to verify and trust.', 'geoboost' ),
				'how'     => __( 'Add at least one link to an external authoritative source (primary research, official docs, news of record). Note: ThirstyAffiliates-style cloaked links on your own domain are not detected as outbound.', 'geoboost' ),
			),
			'missing_internal_link'  => array(
				'title'   => __( 'No internal links', 'geoboost' ),
				'summary' => __( 'Internal links help AI engines understand site topic clusters.', 'geoboost' ),
				'why'     => __( 'A connected site is read as more authoritative than isolated pages.', 'geoboost' ),
				'how'     => __( 'Link to at least one related post on your own site.', 'geoboost' ),
			),
		);
	}
}
