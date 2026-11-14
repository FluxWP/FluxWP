<?php
/**
 * Flux AI & MCP › Skill'ler: liste, ekle/düzenle, aç/kapat, klasör dosyaları, testler, zip içe/dışa aktarma.
 * Skill'ler sohbetten de (flxai_skill_* araçları, "skill-olusturucu") oluşturulur ve düzenlenir.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['page'], $_GET['export'] ) && 'flx-ai-skills' === $_GET['page'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'flxai_skill_export' );
			$s = flxai_skill_get( sanitize_key( wp_unslash( $_GET['export'] ) ) );
			if ( $s ) {
				$zip = flxai_skill_zip( $s );
				nocache_headers();
				if ( is_wp_error( $zip ) ) {
					header( 'Content-Type: text/markdown; charset=utf-8' );
					header( 'Content-Disposition: attachment; filename="SKILL.md"' );
					echo flxai_skill_to_md( $s, null, null, true ); // phpcs:ignore WordPress.Security.EscapeOutput
					exit;
				}
				header( 'Content-Type: application/zip' );
				header( 'Content-Disposition: attachment; filename="' . $s['slug'] . '.zip"' );
				header( 'Content-Length: ' . filesize( $zip ) );
				readfile( $zip ); // phpcs:ignore
				wp_delete_file( $zip );
				exit;
			}
		}
		if ( isset( $_GET['page'], $_GET['skill'], $_GET['download'] ) && 'flx-ai-skills' === $_GET['page'] && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'flxai_skill_file' );
			$s = flxai_skill_get( sanitize_key( wp_unslash( $_GET['skill'] ) ) );
			$p = $s ? flxai_skill_clean_path( wp_unslash( $_GET['download'] ) ) : '';
			if ( $s && ! is_wp_error( $p ) && is_file( flxai_skill_abs_dir( $s ) . '/' . $p ) ) {
				nocache_headers();
				header( 'Content-Type: application/octet-stream' );
				header( 'Content-Disposition: attachment; filename="' . basename( $p ) . '"' );
				readfile( flxai_skill_abs_dir( $s ) . '/' . $p ); // phpcs:ignore
				exit;
			}
		}
		// phpcs:enable
		if ( empty( $_POST['flxai_skills_nonce'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'flxai_skills', 'flxai_skills_nonce' );
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidationSanitization
		$do   = sanitize_key( $post['do'] ?? '' );
		$old  = sanitize_key( $post['old'] ?? '' );
		$back = admin_url( 'admin.php?page=flx-ai-skills' );
		$fail = function ( $e, $edit, $extra = array() ) use ( $back ) {
			set_transient( 'flxai_skills_err_' . get_current_user_id(), $e->get_error_message(), 60 );
			wp_safe_redirect( add_query_arg( array_filter( array_merge( array( 'edit' => $edit, 'msg' => 'error' ), $extra ) ), $back ) );
			exit;
		};
		$args = array( 'msg' => 'saved' );
		if ( 'save' === $do ) {
			$s = flxai_skill_save(
				array(
					'slug'          => (string) ( $post['slug'] ?? '' ),
					'name'          => (string) ( $post['name'] ?? '' ),
					'description'   => (string) ( $post['description'] ?? '' ),
					'body'          => (string) ( $post['body'] ?? '' ),
					'tools'         => (string) ( $post['tools'] ?? '' ),
					'license'       => (string) ( $post['license'] ?? '' ),
					'compatibility' => (string) ( $post['compatibility'] ?? '' ),
					'example'       => (string) ( $post['example'] ?? '' ),
					'status'        => empty( $post['active'] ) ? 'off' : 'active',
				),
				$old
			);
			if ( is_wp_error( $s ) ) {
				$fail( $s, $old ? $old : 'new' );
			}
			$args['edit'] = $s['slug'];
		} elseif ( 'import' === $do ) {
			if ( ! empty( $_FILES['file']['tmp_name'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidationSanitization
				$r = flxai_skill_import_file( $_FILES['file']['tmp_name'], sanitize_file_name( (string) ( $_FILES['file']['name'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.ValidationSanitization
			} else {
				$r = flxai_skill_import_md( (string) ( $post['md'] ?? '' ) );
			}
			if ( is_wp_error( $r ) ) {
				$fail( $r, 'import' );
			}
			set_transient( 'flxai_skills_notes_' . get_current_user_id(), $r, 120 );
			$args['msg'] = 'imported';
		} elseif ( 'settings' === $do ) {
			update_option( 'flxai_skills_restrict', empty( $post['restrict'] ) ? 0 : 1, false );
		} elseif ( 'brand' === $do ) {
			$prof = array();
			foreach ( array_keys( flxai_brand_fields() ) as $k ) {
				if ( isset( $post['brand'][ $k ] ) ) {
					$prof[ $k ] = $post['brand'][ $k ];
				}
			}
			$docs = array();
			foreach ( (array) ( $post['brand_doc'] ?? array() ) as $n => $md ) {
				$docs[ (string) $n ] = (string) $md;
			}
			if ( '' !== trim( (string) ( $post['brand_new_name'] ?? '' ) ) && '' !== trim( (string) ( $post['brand_new_body'] ?? '' ) ) ) {
				$docs[ (string) $post['brand_new_name'] ] = (string) $post['brand_new_body'];
			}
			$r = flxai_brand_save( $prof, $docs );
			if ( is_wp_error( $r ) ) {
				$fail( $r, 'brand' );
			}
			$args['edit'] = 'brand';
		} elseif ( $old && ( $s = flxai_skill_get( $old ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			if ( 'toggle' === $do ) {
				flxai_skill_toggle( $old );
			} elseif ( 'delete' === $do ) {
				flxai_skill_delete( $old );
				$args['msg'] = $s['bundled'] ? 'reset' : 'deleted';
			} elseif ( 'file_save' === $do || 'file_upload' === $do || 'file_delete' === $do ) {
				$path = (string) ( $post['path'] ?? '' );
				if ( 'file_upload' === $do ) {
					if ( empty( $_FILES['upload']['tmp_name'] ) || ! is_uploaded_file( $_FILES['upload']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidationSanitization
						$fail( new WP_Error( 'flxai_skill_file', __( 'Choose a file to upload.', 'flx-ai' ) ), $old );
					}
					$dir  = trim( sanitize_text_field( (string) ( $post['folder'] ?? 'references' ) ), '/' );
					$path = ( '' !== $dir ? $dir . '/' : '' ) . sanitize_file_name( (string) $_FILES['upload']['name'] ); // phpcs:ignore WordPress.Security.ValidationSanitization
					$r    = flxai_skill_file_write( $old, $path, (string) file_get_contents( $_FILES['upload']['tmp_name'] ) ); // phpcs:ignore
				} else {
					$r = flxai_skill_file_write( $old, $path, 'file_delete' === $do ? null : str_replace( "\r\n", "\n", (string) ( $post['content'] ?? '' ) ) );
				}
				if ( is_wp_error( $r ) ) {
					$fail( $r, $old, 'file_save' === $do ? array( 'file' => $path ) : array() );
				}
				$args['edit'] = $old;
				$args['msg']  = 'file_delete' === $do ? 'file_deleted' : 'file_saved';
				$args['tab']  = 'files';
			} elseif ( 'tests_save' === $do ) {
				$trig = array();
				foreach ( preg_split( '/\R/', (string) ( $post['trigger'] ?? '' ) ) as $line ) {
					$line = trim( $line );
					if ( '' === $line ) {
						continue;
					}
					$neg    = 0 === strpos( $line, '-' );
					$trig[] = array(
						'query'          => trim( ltrim( $line, '+-' ) ),
						'should_trigger' => ! $neg,
					);
				}
				$cases = array();
				foreach ( (array) ( $post['case'] ?? array() ) as $k => $c ) {
					$cases[] = array(
						'id'           => (string) ( $k + 1 ),
						'prompt'       => (string) ( $c['prompt'] ?? '' ),
						'expectations' => preg_split( '/\R/', (string) ( $c['expect'] ?? '' ) ),
					);
				}
				$r = flxai_skill_evals_save(
					$old,
					array(
						'trigger_evals' => $trig,
						'evals'         => $cases,
					)
				);
				if ( is_wp_error( $r ) ) {
					$fail( $r, $old, array( 'tab' => 'tests' ) );
				}
				$args['edit'] = $old;
				$args['tab']  = 'tests';
			}
		}
		wp_safe_redirect( add_query_arg( $args, $back ) );
		exit;
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		if ( false === strpos( (string) $hook, 'flx-ai-skills' ) ) {
			return;
		}
		wp_enqueue_script( 'flxai-skills', FLXAI_URL . 'assets/js/skills.js', array(), FLXAI_VERSION, true );
	}
);

function flxai_page_skills() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	flxai_page_head( __( 'Skills', 'flx-ai' ), __( 'Instruction folders the assistant follows, in the open Agent Skills format (the same SKILL.md folders Claude uses). Chats and scheduled tasks use them by name; edit a skill once and every task that uses it follows the new version.', 'flx-ai' ) );
	$err = get_transient( 'flxai_skills_err_' . get_current_user_id() );
	if ( $err && 'error' === ( $_GET['msg'] ?? '' ) ) {
		delete_transient( 'flxai_skills_err_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	} elseif ( isset( $_GET['msg'] ) ) {
		$m = array(
			'saved'        => __( 'Saved.', 'flx-ai' ),
			'imported'     => __( 'Skill imported.', 'flx-ai' ),
			'deleted'      => __( 'Skill deleted.', 'flx-ai' ),
			'reset'        => __( 'Skill reset to the version its plugin ships.', 'flx-ai' ),
			'file_saved'   => __( 'File saved.', 'flx-ai' ),
			'file_deleted' => __( 'File deleted.', 'flx-ai' ),
		)[ sanitize_key( $_GET['msg'] ) ] ?? '';
		$notes = get_transient( 'flxai_skills_notes_' . get_current_user_id() );
		if ( 'imported' === $_GET['msg'] && is_array( $notes ) ) {
			delete_transient( 'flxai_skills_notes_' . get_current_user_id() );
			/* translators: %s: comma separated skill names */
			$m = sprintf( __( 'Imported: %s', 'flx-ai' ), implode( ', ', (array) $notes['imported'] ) );
			foreach ( (array) $notes['notes'] as $n ) {
				$m .= ' · ' . $n;
			}
		}
		if ( $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
	if ( 'brand' === $edit ) {
		flxai_brand_form();
		echo '</div>';
		return;
	}
	if ( 'import' === $edit ) {
		flxai_skill_import_form();
		echo '</div>';
		return;
	}
	if ( '' !== $edit ) {
		$s = 'new' === $edit ? null : flxai_skill_get( $edit );
		$f = isset( $_GET['file'] ) ? wp_unslash( $_GET['file'] ) : null; // phpcs:ignore WordPress.Security.ValidationSanitization
		if ( $s && null !== $f ) {
			flxai_skill_file_form( $s, (string) $f );
		} else {
			flxai_skill_form( $s, $s ? sanitize_key( $_GET['tab'] ?? '' ) : '' );
		}
		echo '</div>';
		return;
	}
	// phpcs:enable

	$skills = flxai_skills_all();
	$used   = array();
	foreach ( flxai_task_list( array( 'limit' => 500 ) ) as $t ) {
		$k = (string) ( $t['meta']['skill'] ?? '' );
		if ( '' !== $k ) {
			$used[ $k ] = ( $used[ $k ] ?? 0 ) + 1;
		}
	}
	echo '<section class="flxai-card flxai-skills">';
	echo '<div class="flxai-bill-head"><h2>' . esc_html__( 'Skills', 'flx-ai' ) . '</h2><div class="flxai-tasks-acts">';
	echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills&edit=brand' ) ) . '">' . esc_html__( 'Brand kit', 'flx-ai' ) . '</a> ';
	echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills&edit=import' ) ) . '">' . esc_html__( 'Import', 'flx-ai' ) . '</a> ';
	echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills&edit=new' ) ) . '">' . esc_html__( 'New skill', 'flx-ai' ) . '</a>';
	echo '</div></div>';
	// Kaynağa göre gruplar: bu sitenin skill'leri, Flux AI & MCP, sonra eklentiler / paketler.
	$groups = array();
	foreach ( $skills as $s ) {
		$g              = $s['bundled'] ? (string) $s['source'] : __( 'This site', 'flx-ai' );
		$groups[ $g ][] = $s;
	}
	uksort(
		$groups,
		function ( $a, $b ) {
			$rank = fn( $k ) => __( 'This site', 'flx-ai' ) === $k ? 0 : ( 'Flux AI & MCP' === $k ? 1 : 2 );
			return $rank( $a ) <=> $rank( $b ) ?: strcmp( $a, $b );
		}
	);
	foreach ( $groups as $flxai_group => $flxai_list ) {
		if ( count( $groups ) > 1 ) {
			echo '<h3 class="flxai-skill-group">' . esc_html( $flxai_group ) . ' <span class="flxai-note">' . esc_html( number_format_i18n( count( $flxai_list ) ) ) . '</span></h3>';
		}
		echo '<div class="flxai-skill-grid">';
		foreach ( $flxai_list as $s ) {
			$n     = (int) ( $used[ $s['slug'] ] ?? 0 );
			$files = flxai_skill_files( $s );
			echo '<article class="flxai-skill' . ( 'active' !== $s['status'] ? ' is-off' : '' ) . '">';
			echo '<div class="flxai-skill-top"><strong>' . esc_html( $s['name'] ) . '</strong>';
			if ( 'active' !== $s['status'] ) {
				echo ' <span class="flxai-pill">' . esc_html__( 'Off', 'flx-ai' ) . '</span>';
			}
			echo '</div><code class="flxai-skill-slug">/' . esc_html( $s['slug'] ) . '</code>';
			echo '<p class="flxai-desc">' . esc_html( $s['description'] ) . '</p>';
			echo '<div class="flxai-skill-meta">';
			if ( $s['bundled'] ) {
				echo '<span class="flxai-pill">' . esc_html( $s['source'] ) . '</span>';
				if ( $s['changed'] ) {
					echo '<span class="flxai-pill is-warn">' . esc_html__( 'Edited', 'flx-ai' ) . '</span>';
				}
			} else {
				echo '<span class="flxai-pill is-ok">' . esc_html__( 'This site', 'flx-ai' ) . '</span>';
			}
			/* translators: %s: version number */
			echo '<span class="flxai-note">' . esc_html( sprintf( __( 'v%s', 'flx-ai' ), $s['version'] ) ) . '</span>';
			if ( $files ) {
				/* translators: %s: number of files */
				echo '<span class="flxai-note">' . esc_html( sprintf( _n( '%s file', '%s files', count( $files ), 'flx-ai' ), number_format_i18n( count( $files ) ) ) ) . '</span>';
			}
			if ( flxai_skill_has_scripts( $s ) ) {
				echo '<span class="flxai-pill is-warn" title="' . esc_attr__( 'Scripts are stored but not run on this server.', 'flx-ai' ) . '">' . esc_html__( 'Scripts', 'flx-ai' ) . '</span>';
			}
			if ( $n ) {
				/* translators: %s: number of tasks */
				echo '<span class="flxai-note">' . esc_html( sprintf( __( 'Tasks: %s', 'flx-ai' ), number_format_i18n( $n ) ) ) . '</span>';
			}
			echo '</div><form method="post" class="flxai-skill-acts">';
			wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
			echo '<input type="hidden" name="old" value="' . esc_attr( $s['slug'] ) . '">';
			echo '<a class="button button-small" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills&edit=' . $s['slug'] ) ) . '">' . esc_html__( 'Edit', 'flx-ai' ) . '</a> ';
			echo '<button type="submit" name="do" value="toggle" class="button button-small">' . esc_html( 'active' === $s['status'] ? __( 'Turn off', 'flx-ai' ) : __( 'Turn on', 'flx-ai' ) ) . '</button> ';
			echo '<a class="button button-small" href="' . esc_url( wp_nonce_url( admin_url( 'admin.php?page=flx-ai-skills&export=' . $s['slug'] ), 'flxai_skill_export' ) ) . '">' . esc_html__( 'Export', 'flx-ai' ) . '</a> ';
			if ( ! $s['bundled'] ) {
				printf(
					'<button type="submit" name="do" value="delete" class="button button-small button-link-delete" data-flx-confirm="%1$s" data-flx-confirm-ok="%2$s" data-flx-confirm-danger>%2$s</button>',
					/* translators: %s: skill name */
					esc_attr( sprintf( __( 'Delete the skill "%s"? Tasks that use it will run without it.', 'flx-ai' ), $s['name'] ) ),
					esc_html__( 'Delete', 'flx-ai' )
				);
			} elseif ( $s['changed'] || flxai_skill_is_saved( $s['slug'] ) ) {
				printf(
					'<button type="submit" name="do" value="delete" class="button button-small" data-flx-confirm="%1$s" data-flx-confirm-ok="%2$s">%2$s</button>',
					/* translators: %s: skill name */
					esc_attr( sprintf( __( 'Reset "%s" to the version its plugin ships? Your changes are lost.', 'flx-ai' ), $s['name'] ) ),
					esc_html__( 'Reset', 'flx-ai' )
				);
			}
			echo '</form></article>';
		}
		echo '</div>';
	}
	echo '<p class="flxai-note flxai-tasks-tip">' . esc_html__( 'Tip: start a chat message with /name to use a skill, e.g. "/gunluk-brifing only donations"; several work together ("/a /b …"). Ask "/skill-olusturucu" to write and test a new one. Plugins can ship ready skill folders with the flxai_skills filter.', 'flx-ai' ) . '</p>';
	echo '<form method="post" class="flxai-skill-settings">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="settings"><label class="flxai-check"><input type="checkbox" name="restrict" value="1"' . checked( flxai_skills_restrict_tools(), true, false ) . ' onchange="this.form.submit()"> ' . esc_html__( 'When a message uses a skill, offer only the tools in its allowed-tools list', 'flx-ai' ) . '</label>';
	echo '<noscript><button type="submit" class="button button-small">' . esc_html__( 'Save', 'flx-ai' ) . '</button></noscript></form>';
	echo '</section></div>';
}

/** Ekle / düzenle (+ dosyalar ve testler sekmeleri). */
function flxai_skill_form( $s, $tab = '' ) {
	$base = admin_url( 'admin.php?page=flx-ai-skills&edit=' . ( $s ? $s['slug'] : 'new' ) );
	if ( $s ) {
		$tabs = array(
			''      => __( 'SKILL.md', 'flx-ai' ),
			'files' => __( 'Files', 'flx-ai' ),
			'tests' => __( 'Tests', 'flx-ai' ),
		);
		echo '<nav class="nav-tab-wrapper flxai-skill-tabs">';
		foreach ( $tabs as $k => $label ) {
			echo '<a class="nav-tab' . ( $k === $tab ? ' nav-tab-active' : '' ) . '" href="' . esc_url( '' === $k ? $base : add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		if ( 'files' === $tab ) {
			flxai_skill_files_card( $s );
			return;
		}
		if ( 'tests' === $tab ) {
			flxai_skill_tests_card( $s );
			return;
		}
	}
	$names = array_keys( flxai_tools_for_user( get_current_user_id() ) );
	echo '<form method="post" class="flxai-card flxai-task-form">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="save"><input type="hidden" name="old" value="' . esc_attr( $s ? $s['slug'] : '' ) . '">';
	echo '<h2>' . esc_html( $s ? __( 'Edit skill', 'flx-ai' ) : __( 'New skill', 'flx-ai' ) ) . '</h2>';
	if ( $s && $s['bundled'] ) {
		/* translators: %s: plugin name */
		echo '<p class="flxai-note">' . esc_html( sprintf( __( 'This skill comes with %s. Your edits are kept on this site; "Reset" brings back the original.', 'flx-ai' ), $s['source'] ) ) . '</p>';
	}
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th><label for="flxai-skill-name">' . esc_html__( 'Title', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-name" name="name" type="text" class="regular-text" maxlength="190" required value="' . esc_attr( $s ? $s['name'] : '' ) . '" placeholder="' . esc_attr__( 'Donor review', 'flx-ai' ) . '"></td></tr>';
	echo '<tr><th><label for="flxai-skill-slug">' . esc_html__( 'Name', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-slug" name="slug" type="text" class="regular-text code" maxlength="64" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="' . esc_attr( $s ? $s['slug'] : '' ) . '" placeholder="bagisci-incelemesi"' . ( $s && $s['bundled'] ? ' readonly' : '' ) . '>';
	echo '<p class="description">' . esc_html__( 'The skill folder name: lowercase letters, numbers and dashes, up to 64; cannot contain "claude" or "anthropic". Used in chat as /name. Left empty, it is made from the title.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-desc">' . esc_html__( 'Description', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-desc" name="description" rows="3" class="large-text" maxlength="1024" required data-count>' . esc_textarea( $s ? $s['description'] : '' ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'What it does and when to use it, with the words users actually say (e.g. "Use when the user asks for a donor review or loyal donors"). The assistant picks skills by this. Up to 1024 characters, no < or >.', 'flx-ai' ) . ' <span class="flxai-count"></span></p></td></tr>';
	echo '<tr><th><label for="flxai-skill-body">' . esc_html__( 'Instructions', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-body" name="body" rows="16" class="large-text code" required>' . esc_textarea( $s ? $s['body'] : '' ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'The SKILL.md body, Markdown. Step by step: what to look at, which tools to use, how the result should look. Put long details in references/ files (Files tab) and mention them here.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-tools">' . esc_html__( 'Allowed tools', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-tools" name="tools" type="text" class="large-text code" list="flxai-tool-names" value="' . esc_attr( $s ? implode( ' ', $s['tools'] ) : '' ) . '">';
	echo '<datalist id="flxai-tool-names">';
	foreach ( $names as $n ) {
		echo '<option value="' . esc_attr( $n ) . '">';
	}
	echo '</datalist><p class="description">' . esc_html__( 'allowed-tools: tool names separated by spaces (see Tools); a trailing * matches a prefix (flxcrm_*). A hint for the assistant; with the setting on the Skills page they also limit the tools.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-example">' . esc_html__( 'Example output', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-example" name="example" rows="6" class="large-text code">' . esc_textarea( $s ? $s['example'] : '' ) . '</textarea><p class="description">' . esc_html__( 'Saved as references/examples.md.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-license">' . esc_html__( 'License', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-license" name="license" type="text" class="regular-text" maxlength="200" value="' . esc_attr( $s ? $s['license'] : '' ) . '" placeholder="MIT"></td></tr>';
	echo '<tr><th><label for="flxai-skill-compat">' . esc_html__( 'Compatibility', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-compat" name="compatibility" type="text" class="large-text" maxlength="500" value="' . esc_attr( $s ? $s['compatibility'] : '' ) . '" placeholder="' . esc_attr__( 'Needs Flux CRM', 'flx-ai' ) . '"></td></tr>';
	echo '<tr><th>' . esc_html__( 'Status', 'flx-ai' ) . '</th><td><label class="flxai-check"><input type="checkbox" name="active" value="1"' . checked( ! $s || 'active' === $s['status'], true, false ) . '> ' . esc_html__( 'Active', 'flx-ai' ) . '</label></td></tr>';
	echo '</table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills' ) ) . '">' . esc_html__( 'Back', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}

/** Dosyalar sekmesi: liste, yükle, yeni dosya. */
function flxai_skill_files_card( array $s ) {
	$files = flxai_skill_files( $s );
	$base  = admin_url( 'admin.php?page=flx-ai-skills&edit=' . $s['slug'] );
	echo '<section class="flxai-card"><h2>' . esc_html__( 'Skill folder', 'flx-ai' ) . '</h2>';
	echo '<p class="flxai-note">' . esc_html__( 'references/ holds documents the assistant reads only when needed, assets/ templates and images, scripts/ code (stored, not run on this server), evals/ tests. Files are also visible in Flux Files.', 'flx-ai' ) . '</p>';
	if ( $s['bundled'] && ! flxai_skill_is_saved( $s['slug'] ) ) {
		echo '<p class="flxai-note">' . esc_html__( 'Changing a file keeps an edited copy of this skill on the site.', 'flx-ai' ) . '</p>';
	}
	echo '<table class="widefat striped flxai-skill-files"><thead><tr><th>' . esc_html__( 'File', 'flx-ai' ) . '</th><th>' . esc_html__( 'Size', 'flx-ai' ) . '</th><th></th></tr></thead><tbody>';
	echo '<tr><td><code>SKILL.md</code></td><td>—</td><td><a href="' . esc_url( $base ) . '">' . esc_html__( 'Edit', 'flx-ai' ) . '</a></td></tr>';
	foreach ( $files as $f ) {
		echo '<tr><td><code>' . esc_html( $f['path'] ) . '</code></td><td>' . esc_html( size_format( $f['size'] ) ) . '</td><td class="flxai-file-acts">';
		echo '<a href="' . esc_url( add_query_arg( 'file', rawurlencode( $f['path'] ), $base ) ) . '">' . esc_html__( 'Open', 'flx-ai' ) . '</a> · ';
		echo '<a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'skill' => $s['slug'], 'download' => rawurlencode( $f['path'] ) ), admin_url( 'admin.php?page=flx-ai-skills' ) ), 'flxai_skill_file' ) ) . '">' . esc_html__( 'Download', 'flx-ai' ) . '</a> · ';
		echo '<form method="post" class="flxai-inline">';
		wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
		echo '<input type="hidden" name="old" value="' . esc_attr( $s['slug'] ) . '"><input type="hidden" name="path" value="' . esc_attr( $f['path'] ) . '">';
		/* translators: %s: file path */
		echo '<button type="submit" name="do" value="file_delete" class="button-link button-link-delete" data-flx-confirm="' . esc_attr( sprintf( __( 'Delete %s?', 'flx-ai' ), $f['path'] ) ) . '" data-flx-confirm-ok="' . esc_attr__( 'Delete', 'flx-ai' ) . '" data-flx-confirm-danger>' . esc_html__( 'Delete', 'flx-ai' ) . '</button></form>';
		echo '</td></tr>';
	}
	echo '</tbody></table>';
	echo '<div class="flxai-skill-file-new">';
	echo '<form method="post" enctype="multipart/form-data">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="old" value="' . esc_attr( $s['slug'] ) . '"><input type="hidden" name="do" value="file_upload">';
	echo '<label>' . esc_html__( 'Upload to', 'flx-ai' ) . ' <select name="folder">';
	foreach ( FLXAI_SKILL_SUBDIRS as $d ) {
		echo '<option value="' . esc_attr( $d ) . '">' . esc_html( $d ) . '/</option>';
	}
	echo '</select></label> <input type="file" name="upload" required> <button type="submit" class="button">' . esc_html__( 'Upload', 'flx-ai' ) . '</button></form>';
	echo '<a class="button" href="' . esc_url( add_query_arg( 'file', 'references/', $base ) ) . '">' . esc_html__( 'New text file', 'flx-ai' ) . '</a>';
	echo '</div></section>';
}

/** Dosya aç / düzenle (metin) ya da yeni dosya. */
function flxai_skill_file_form( array $s, $path ) {
	$base  = admin_url( 'admin.php?page=flx-ai-skills&edit=' . $s['slug'] );
	$isnew = '' === $path || '/' === substr( $path, -1 );
	$r     = $isnew ? null : flxai_skill_file_read( $s, $path, 0, 2000000 );
	echo '<form method="post" class="flxai-card flxai-task-form">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="file_save"><input type="hidden" name="old" value="' . esc_attr( $s['slug'] ) . '">';
	/* translators: %s: skill name */
	echo '<h2>' . esc_html( sprintf( __( '%s · file', 'flx-ai' ), $s['name'] ) ) . '</h2>';
	if ( is_wp_error( $r ) ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html( $r->get_error_message() ) . '</p></div>';
	} elseif ( $r && empty( $r['text'] ) ) {
		echo '<p class="flxai-note">' . esc_html( $r['note'] ) . ' (' . esc_html( size_format( $r['size'] ) ) . ')</p><p><a class="button" href="' . esc_url( add_query_arg( 'tab', 'files', $base ) ) . '">' . esc_html__( 'Back', 'flx-ai' ) . '</a></p></form>';
		return;
	}
	echo '<p><label for="flxai-skill-path"><strong>' . esc_html__( 'Path', 'flx-ai' ) . '</strong></label><br><input id="flxai-skill-path" name="path" type="text" class="large-text code" required value="' . esc_attr( $path ) . '" placeholder="references/guide.md"' . ( $isnew ? '' : ' readonly' ) . '></p>';
	echo '<p><textarea name="content" rows="24" class="large-text code">' . esc_textarea( is_array( $r ) ? (string) ( $r['content'] ?? '' ) : '' ) . '</textarea></p>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( add_query_arg( 'tab', 'files', $base ) ) . '">' . esc_html__( 'Back', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}

/** Testler sekmesi: tetiklenme istemleri, fonksiyonel testler, çalıştır + sonuçlar. */
function flxai_skill_tests_card( array $s ) {
	$ev   = flxai_skill_evals_get( $s );
	$res  = flxai_skill_eval_results( $s );
	$trig = implode(
		"\n",
		array_map( fn( $t ) => ( $t['should_trigger'] ? '+ ' : '- ' ) . $t['query'], $ev['trigger_evals'] )
	);
	echo '<section class="flxai-card flxai-skill-tests" data-skill="' . esc_attr( $s['slug'] ) . '">';
	echo '<form method="post">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="tests_save"><input type="hidden" name="old" value="' . esc_attr( $s['slug'] ) . '">';
	echo '<h2>' . esc_html__( 'Trigger test', 'flx-ai' ) . '</h2>';
	echo '<p class="flxai-note">' . esc_html__( 'Does the assistant pick this skill from its name and description alone? One prompt per line: "+" should use the skill, "-" should not. Aim for 90% or more.', 'flx-ai' ) . '</p>';
	echo '<textarea name="trigger" rows="8" class="large-text code" placeholder="+ …&#10;- …">' . esc_textarea( $trig ) . '</textarea>';
	echo '<h2>' . esc_html__( 'Functional tests', 'flx-ai' ) . '</h2>';
	echo '<p class="flxai-note">' . esc_html__( 'A request and what a good answer must contain (one check per line). The test answers it with and without the skill (no tools, nothing is changed) and compares checks, tokens and cost.', 'flx-ai' ) . '</p>';
	$cases   = $ev['evals'];
	$cases[] = array(
		'id'           => '',
		'prompt'       => '',
		'expectations' => array(),
	);
	foreach ( array_slice( $cases, 0, 10 ) as $k => $c ) {
		echo '<div class="flxai-skill-case"><label><strong>' . esc_html( '' === $c['id'] ? __( 'New test', 'flx-ai' ) : sprintf( '#%s', $c['id'] ) ) . '</strong></label>';
		echo '<textarea name="case[' . (int) $k . '][prompt]" rows="2" class="large-text" placeholder="' . esc_attr__( 'Request', 'flx-ai' ) . '">' . esc_textarea( $c['prompt'] ) . '</textarea>';
		echo '<textarea name="case[' . (int) $k . '][expect]" rows="3" class="large-text code" placeholder="' . esc_attr__( 'Checks, one per line', 'flx-ai' ) . '">' . esc_textarea( implode( "\n", $c['expectations'] ) ) . '</textarea></div>';
	}
	echo '<p class="submit"><button type="submit" class="button">' . esc_html__( 'Save tests', 'flx-ai' ) . '</button></p></form>';

	echo '<h2>' . esc_html__( 'Run', 'flx-ai' ) . '</h2><p class="flxai-note">' . esc_html__( 'Each run uses the chat model and is billed like a short chat (purpose "skill_test" in Logs).', 'flx-ai' ) . '</p>';
	echo '<p class="flxai-skill-run">';
	echo '<button type="button" class="button button-primary" data-run="trigger"' . disabled( ! $ev['trigger_evals'], true, false ) . '>' . esc_html__( 'Run trigger test', 'flx-ai' ) . '</button> ';
	foreach ( $ev['evals'] as $c ) {
		/* translators: %s: test id */
		echo '<button type="button" class="button" data-run="functional" data-id="' . esc_attr( $c['id'] ) . '">' . esc_html( sprintf( __( 'Run test #%s', 'flx-ai' ), $c['id'] ) ) . '</button> ';
	}
	echo '</p><div class="flxai-skill-results" data-results="' . esc_attr( wp_json_encode( $res ) ) . '"></div>';
	echo '</section>';
}

function flxai_skill_import_form() {
	echo '<form method="post" enctype="multipart/form-data" class="flxai-card flxai-task-form">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="import">';
	echo '<h2>' . esc_html__( 'Import skills', 'flx-ai' ) . '</h2>';
	echo '<p class="flxai-note">' . esc_html__( 'Upload a skill folder as .zip (one or more folders, each with a SKILL.md, e.g. exported from Claude) or a single SKILL.md, or paste its content. A skill with the same name is updated. Scripts are stored but not run.', 'flx-ai' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th><label for="flxai-skill-file">' . esc_html__( 'File', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-file" name="file" type="file" accept=".zip,.skill,.md,application/zip,text/markdown,text/plain"></td></tr>';
	echo '<tr><th><label for="flxai-skill-md">' . esc_html__( 'or paste SKILL.md', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-md" name="md" rows="14" class="large-text code" placeholder="---&#10;name: bagisci-incelemesi&#10;description: …&#10;---&#10;&#10;# …"></textarea></td></tr>';
	echo '</table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Import', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills' ) ) . '">' . esc_html__( 'Cancel', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}

/** Marka kiti: tasarım ve yazım skill'lerinin okuduğu kurum bilgileri, renkler, logolar ve belgeler. */
function flxai_brand_form() {
	$raw  = flxai_brand_raw();
	$def  = flxai_brand_defaults();
	$p    = $raw['profile'];
	$back = admin_url( 'admin.php?page=flx-ai-skills' );
	echo '<p><a href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'Skills', 'flx-ai' ) . '</a></p>';
	echo '<form method="post" class="flxai-card flxai-brand">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="brand">';
	echo '<h2>' . esc_html__( 'Brand kit', 'flx-ai' ) . '</h2>';
	echo '<p class="description">' . esc_html__( 'Design and writing skills read this before they start: colors and logos for visuals, voice documents for posts. Empty fields use the site name, logo and theme colors. The assistant can also update it from the chat (you approve each change).', 'flx-ai' ) . '</p>';
	$labels = array(
		'name'          => array( __( 'Organisation name', 'flx-ai' ), $def['name'] ),
		'tagline'       => array( __( 'Tagline', 'flx-ai' ), $def['tagline'] ),
		'website'       => array( __( 'Website shown on visuals', 'flx-ai' ), $def['website'] ),
		'phone'         => array( __( 'Phone / WhatsApp', 'flx-ai' ), '' ),
		'handle'        => array( __( 'Social media handle or hashtag', 'flx-ai' ), '' ),
		'color_primary' => array( __( 'Primary color', 'flx-ai' ), '' ),
		'color_accent'  => array( __( 'Accent color', 'flx-ai' ), '' ),
		'color_dark'    => array( __( 'Dark color (backgrounds)', 'flx-ai' ), '' ),
		'color_light'   => array( __( 'Light color (backgrounds)', 'flx-ai' ), '' ),
		'logo_id'       => array( __( 'Logo (media ID)', 'flx-ai' ), $def['logo_id'] ? (string) $def['logo_id'] : '' ),
		'logo_light_id' => array( __( 'White logo for dark backgrounds (media ID)', 'flx-ai' ), '' ),
		'partner_ids'   => array( __( 'Partner logos (media IDs, comma separated)', 'flx-ai' ), '' ),
		'notes'         => array( __( 'Design notes', 'flx-ai' ), '' ),
	);
	echo '<table class="form-table">';
	foreach ( flxai_brand_fields() as $k => $type ) {
		$v = $p[ $k ] ?? '';
		$v = is_array( $v ) ? implode( ', ', $v ) : (string) ( 0 === $v ? '' : $v );
		echo '<tr><th><label for="flxai-brand-' . esc_attr( $k ) . '">' . esc_html( $labels[ $k ][0] ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="flxai-brand-' . esc_attr( $k ) . '" name="brand[' . esc_attr( $k ) . ']" rows="3" class="large-text" placeholder="' . esc_attr__( 'e.g. Rounded shapes, lots of green, real photos of our volunteers', 'flx-ai' ) . '">' . esc_textarea( $v ) . '</textarea>';
		} elseif ( 'color' === $type ) {
			echo '<input type="text" id="flxai-brand-' . esc_attr( $k ) . '" name="brand[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '" class="regular-text code" placeholder="#rrggbb" pattern="#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})"> ';
			echo '<span class="flxai-brand-swatch" style="background:' . esc_attr( '' !== $v ? $v : 'transparent' ) . '"></span>';
		} else {
			echo '<input type="text" id="flxai-brand-' . esc_attr( $k ) . '" name="brand[' . esc_attr( $k ) . ']" value="' . esc_attr( $v ) . '" class="regular-text" placeholder="' . esc_attr( (string) $labels[ $k ][1] ) . '">';
			if ( 'ids' === $type ) {
				foreach ( array_filter( array_map( 'absint', explode( ',', $v ) ) ) as $flxai_id ) {
					$flxai_u = wp_get_attachment_url( $flxai_id );
					echo $flxai_u ? ' <img class="flxai-brand-logo" src="' . esc_url( $flxai_u ) . '" alt="">' : '';
				}
			}
			if ( 'id' === $type && (int) $v ) {
				$flxai_u = wp_get_attachment_url( (int) $v );
				if ( $flxai_u ) {
					echo ' <img class="flxai-brand-logo" src="' . esc_url( $flxai_u ) . '" alt="">';
				}
			}
		}
		echo '</td></tr>';
	}
	if ( $def['theme_palette'] ) {
		echo '<tr><th>' . esc_html__( 'Theme colors', 'flx-ai' ) . '</th><td class="flxai-brand-palette">';
		foreach ( $def['theme_palette'] as $c ) {
			echo '<span title="' . esc_attr( $c['name'] ) . '"><i class="flxai-brand-swatch" style="background:' . esc_attr( $c['color'] ) . '"></i><code>' . esc_html( $c['color'] ) . '</code></span> ';
		}
		echo '</td></tr>';
	}
	echo '</table>';
	echo '<h3>' . esc_html__( 'Documents', 'flx-ai' ) . '</h3>';
	echo '<p class="description">' . esc_html__( 'Markdown documents skills read, e.g. about-me.md and voice.md (written by the voice-builder skill). Clear a document to delete it.', 'flx-ai' ) . '</p>';
	foreach ( $raw['docs'] as $n => $md ) {
		echo '<p><label><strong><code>' . esc_html( $n ) . '</code></strong></label><br><textarea name="brand_doc[' . esc_attr( $n ) . ']" rows="8" class="large-text code">' . esc_textarea( $md ) . '</textarea></p>';
	}
	echo '<p><input type="text" name="brand_new_name" class="regular-text code" placeholder="voice.md"></p><p><textarea name="brand_new_body" rows="5" class="large-text code" placeholder="' . esc_attr__( 'New document (Markdown)', 'flx-ai' ) . '"></textarea></p>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button></p></form>';
}
