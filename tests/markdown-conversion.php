<?php
/** Shared conversion regressions: isolated stubs, no WordPress database or saved settings. */
return ( static function (): array {
	$keys = array( 'llmf_test_options', 'llmf_test_posts', 'llmf_test_meta', 'llmf_test_filters', 'llmf_test_parse_blocks_callback', 'llmf_test_render_block' );
	$saved = array();
	foreach ( $keys as $key ) { $saved[ $key ] = array_key_exists( $key, $GLOBALS ) ? array( $GLOBALS[ $key ] ) : array(); }
	$cases = array();
	try {
		$GLOBALS['llmf_test_filters'] = array();
		$GLOBALS['llmf_test_meta'] = array();
		$GLOBALS['llmf_test_options'] = array();
		$serialize = static function ( array $block ) use ( &$serialize ): string {
			$html = '';
			$i = 0;
			foreach ( $block['innerContent'] ?? array( $block['innerHTML'] ?? '' ) as $chunk ) {
				$html .= is_string( $chunk ) ? $chunk : $serialize( $block['innerBlocks'][ $i ++ ] );
			}
			return $html;
		};
		$GLOBALS['llmf_test_render_block'] = $serialize;
		$convert = static function ( array $blocks, string $profile, string $canonical = 'https://example.test/subsite/article/' ) use ( &$cases ): string {
			$GLOBALS['llmf_test_parse_blocks_callback'] = static function () use ( $blocks ) { return $blocks; };
			$GLOBALS['llmf_test_filters']['post_link'] = array( static function () use ( $canonical ) { return $canonical; } );
			$post = new WP_Post( array( 'ID' => 9801, 'post_name' => 'conversion', 'post_title' => 'Conversion', 'post_content' => 'isolated conversion source' ) );
			$GLOBALS['llmf_test_posts'] = array( $post->ID => $post );
			$options = ( new LLMFriendly\Options() )->candidate( array( 'content_profile' => $profile ) );
			$exporter = new LLMFriendly\Exporter( $options );
			$md = $exporter->inspect( $post )['markdown'];
			$cases[] = array( 'profile' => $profile, 'blocks' => $blocks, 'markdown' => $md );
			return ltrim( explode( "\n# Conversion\n", $md, 2 )[1], "\n" );
		};
		$html_block = static function ( string $html ): array { return array( 'blockName' => 'core/freeform', 'innerHTML' => $html, 'innerContent' => array( $html ) ); };
		foreach ( array( 'legacy', 'enhanced' ) as $profile ) {
			$old = array( 'blockName' => 'core/list', 'innerBlocks' => array(), 'innerHTML' => '<ul class="wp-block-list"><li><strong>Brains</strong> &amp; knowledge</li><li>Experience <code>&lt;T&gt;</code></li><li><a href="../guide/">Procedures</a></li></ul>' );
			$md = $convert( array( $html_block( '<p>Before.</p>' ), $old, $html_block( '<p>After.</p>' ) ), $profile );
			assert_contains_text( "Before.\n\n- **Brains** & knowledge\n- Experience `<T>`\n- [Procedures](https://example.test/subsite/guide/)\n\nAfter.", $md, $profile . ': old Gutenberg lists keep all items, inline markup and surrounding paragraphs.' );
			assert_occurrences( '- Experience', $md, 1, $profile . ': old-list fallback does not duplicate items.' );
			assert_not_contains_text( "\n-", $convert( array( array( 'blockName' => 'core/list', 'innerBlocks' => array(), 'innerHTML' => '<ul></ul>' ) ), $profile ), $profile . ': empty lists do not invent items.' );

			$deep = array( 'blockName' => 'core/list', 'innerHTML' => '<ul><li>Third level $1</li><li>Third sibling</li></ul>', 'innerBlocks' => array() );
			$child = array( 'blockName' => 'core/list-item', 'innerHTML' => '<li>Child</li>', 'innerBlocks' => array( $deep ), 'innerContent' => array( '<li>Child', null, '</li>' ) );
			$nested = array( 'blockName' => 'core/list', 'attrs' => array( 'ordered' => true, 'start' => 12 ), 'innerBlocks' => array( $child ), 'innerContent' => array( '<ol start="12">', null, '</ol>' ) );
			$parent = array( 'blockName' => 'core/list-item', 'innerHTML' => '<li><p>Parent<br>continued</p><p>Second paragraph.</p><p>After nested.</p></li>', 'innerBlocks' => array( $nested ), 'innerContent' => array( '<li><p>Parent<br>continued</p><p>Second paragraph.</p>', null, '<p>After nested.</p></li>' ) );
			$sibling = array( 'blockName' => 'core/list-item', 'innerHTML' => '<li>Sibling</li>' );
			$list = array( 'blockName' => 'core/list', 'attrs' => array( 'ordered' => true, 'start' => 9 ), 'innerBlocks' => array( $parent, $sibling ), 'innerContent' => array( '<ol start="9">', null, null, '</ol>' ) );
			$md = $convert( array( $list ), $profile );
			assert_contains_text( "9. Parent\n   continued\n\n   Second paragraph.\n\n   12. Child\n       - Third level $1\n       - Third sibling\n\n   After nested.\n10. Sibling", $md, $profile . ': list slots, multiline paragraphs, numbering and three levels retain their positions and marker widths.' );
			assert_occurrences( 'Third level $1', $md, 1, $profile . ': list serialization does not duplicate nested content or interpret replacement backreferences.' );
			$classic = $convert( array( $html_block( $serialize( $list ) ) ), $profile );
			assert_true( $classic === $md, $profile . ': Classic and new Gutenberg list trees share serialization.' );
			$md = $convert( array( $html_block( '<ol start="5"><li>Five<ul><li>First child</li><li>Second child<ol start="3"><li>Deep</li></ol></li></ul></li><li>Six</li></ol>' ) ), $profile );
			assert_contains_text( "5. Five\n   - First child\n   - Second child\n\n     3. Deep\n6. Six", $md, $profile . ': a nested unordered list does not split its ordered parent.' );

			foreach ( array( 'core/quote', 'core/freeform' ) as $name ) {
				$quote = $html_block( '<blockquote><p>First quote.</p><p>Second <em>quote</em>.</p><cite>Role <a href="../source/"><strong>Name</strong></a></cite></blockquote><p>Following author paragraph.</p>' );
				$quote['blockName'] = $name;
				$md = $convert( array( $quote ), $profile );
				assert_contains_text( "> First quote.\n>\n> Second *quote*.\n>\n> Role [**Name**](https://example.test/subsite/source/)\n\nFollowing author paragraph.", $md, $profile . ': ' . $name . ' retains linked attribution inside its quote.' );
			}
			$md = $convert( array( $html_block( '<blockquote>Raw <em>quote</em>.<cite>Client</cite></blockquote><blockquote><p>No attribution.</p></blockquote><p>After quotes.</p>' ) ), $profile );
			assert_contains_text( "> Raw *quote*.\n>\n> Client\n\n> No attribution.\n\nAfter quotes.", $md, $profile . ': direct quote text and a quote without cite remain intact.' );

			$inline = '<p>Before<em> body </em>after <strong> Form data. </strong>end. <code>https://&lt;...&gt;/points/get/</code> <kbd>&lt;key&gt;</kbd> <code>&amp;lt;literal&amp;gt; &amp; a `b`</code> <code>&lt;img src=x onerror=alert(1)&gt;</code></p><p><code><a href="https://www.w3.org/WAI/standards-guidelines/wcag/">a11y</a> &amp; performance coverage</code> <a href="../guide/?a=1&amp;b=2#part"><code>x`y</code></a></p>';
			$md = $convert( array( $html_block( $inline ) ), $profile );
			foreach ( array( 'Before *body* after **Form data.** end.', '`https://<...>/points/get/`', '`<key>`', '`` &lt;literal&gt; & a `b` ``', '`<img src=x onerror=alert(1)>`', '[`a11y`](https://www.w3.org/WAI/standards-guidelines/wcag/) `& performance coverage`', '[`` x`y ``](https://example.test/subsite/guide/?a=1&b=2#part)' ) as $expected ) {
				assert_contains_text( $expected, $md, $profile . ': inline text, code and links survive without double entity decoding.' );
			}
			assert_contains_text( '[](https://example.test/empty/)', $convert( array( $html_block( '<ul><li>Text<a href="/empty/"></a></li></ul>' ) ), $profile ), $profile . ': an empty link preserves its destination without adding visible text.' );
			$md = $convert( array( $html_block( '<p>Before<a href="/guide/"> label </a>after <code> padded </code> / <code> </code> / <code></code>end.</p>' ) ), $profile );
			assert_contains_text( 'Before [label](https://example.test/guide/) after `  padded  ` / ` ` / end.', $md, $profile . ': link whitespace stays outside delimiters and all-space code gains no padding.' );
			$md = $convert( array( $html_block( '<p><code>&lt;a href="/literal"&gt;Example &lt;...&gt;&lt;/a&gt;</code> <a href=../guide/>Unquoted</a></p>' ) ), $profile );
			assert_contains_text( '`<a href="/literal">Example <...></a>`', $md, $profile . ': resolving attributes does not rewrite literal HTML inside code.' );
			assert_contains_text( '[Unquoted](https://example.test/subsite/guide/)', $md, $profile . ': actual unquoted href attributes resolve against canonical.' );

			$references = '<p><a href="#contact">Contact</a> <a href="/root/">Root</a> <a href="../guide/?q=1&amp;x=2#part">Guide</a> <a href="?view=all#part">Query</a> <a href="https://example.org/a(b)">Absolute</a></p>';
			$md = $convert( array( $html_block( $references ) ), $profile, 'https://example.test/subsite/speaker/' );
			foreach ( array( '[Contact](https://example.test/subsite/speaker/#contact)', '[Root](https://example.test/root/)', '[Guide](https://example.test/subsite/guide/?q=1&x=2#part)', '[Query](https://example.test/subsite/speaker/?view=all#part)', '[Absolute](https://example.org/a%28b%29)' ) as $expected ) {
				assert_contains_text( $expected, $md, $profile . ': links resolve against canonical HTML, including subdirectory installations.' );
			}
			$md = $convert( array( array( 'blockName' => 'core/image', 'innerHTML' => '<figure><img src="../images/a.png" alt="Relative"></figure>' ), $html_block( '<figure><img src="/root.png" alt="Root"></figure>' ) ), $profile );
			assert_contains_text( '(https://example.test/subsite/images/a.png)', $md, $profile . ': Gutenberg image sources resolve against canonical.' );
			assert_contains_text( '(https://example.test/root.png)', $md, $profile . ': Classic image sources resolve against canonical origin.' );
			foreach ( array( 'javascript:alert(1)', 'data:text/html,x', '//evil.test/x', 'https://user:secret@example.test/x' ) as $unsafe ) {
				$md = $convert( array( $html_block( '<p><a href="' . $unsafe . '">Safe label</a></p>' ) ), $profile );
				assert_contains_text( 'Safe label', $md, $profile . ': rejected destinations retain their labels.' );
				assert_not_contains_text( '](', $md, $profile . ': unsafe schemes, protocol-relative references and credentials stay rejected.' );
			}
		}
		// Old caches must not mask a converter update at the same plugin version/profile.
		$post = $GLOBALS['llmf_test_posts'][9801];
		$options = new LLMFriendly\Options();
		$exporter = new LLMFriendly\Exporter( $options );
		$meta = call_private( $exporter, 'markdown_metadata_for_post', array( $post ) );
		$old_key = 'llmf_md_' . md5( LLMF_VERSION . '|' . LLMFriendly\Content::FORMAT . '|legacy|' . $post->ID . '|' . call_private( $exporter, 'post_modified_timestamp', array( $post ) ) . '|0|' . call_private( $exporter, 'metadata_hash', array( $meta ) ) . '|' . LLMFriendly\Content::fingerprint( $post ) );
		set_transient( $old_key, 'OLD-CACHED-CONVERSION', 3600 );
		assert_not_contains_text( 'OLD-CACHED-CONVERSION', call_private( $exporter, 'public_markdown_for_post', array( $post ) ), 'Converter format revision bypasses an old warmed Markdown cache without changing settings.' );
		delete_transient( $old_key );
	} finally {
		foreach ( $saved as $key => $value ) {
			if ( $value ) { $GLOBALS[ $key ] = $value[0]; } else { unset( $GLOBALS[ $key ] ); }
		}
	}
	return $cases;
} )();
