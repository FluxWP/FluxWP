<?php
/**
 * Flux AI & MCP › Skill'ler: liste, ekle/düzenle, aç/kapat, SKILL.md dışa/içe aktarma.
 * Skill'ler sohbetten de (flxai_skill_* araçları) oluşturulur ve düzenlenir.
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
				nocache_headers();
				header( 'Content-Type: text/markdown; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $s['slug'] . '-SKILL.md"' );
				echo flxai_skill_to_md( $s ); // phpcs:ignore WordPress.Security.EscapeOutput
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
		$fail = function ( $e, $edit ) use ( $back ) {
			set_transient( 'flxai_skills_err_' . get_current_user_id(), $e->get_error_message(), 60 );
			wp_safe_redirect( add_query_arg( array_filter( array( 'edit' => $edit, 'msg' => 'error' ) ), $back ) );
			exit;
		};
		$msg  = 'saved';
		if ( 'save' === $do ) {
			$s = flxai_skill_save(
				array(
					'slug'        => (string) ( $post['slug'] ?? '' ),
					'name'        => (string) ( $post['name'] ?? '' ),
					'description' => (string) ( $post['description'] ?? '' ),
					'body'        => (string) ( $post['body'] ?? '' ),
					'tools'       => (string) ( $post['tools'] ?? '' ),
					'example'     => (string) ( $post['example'] ?? '' ),
					'status'      => empty( $post['active'] ) ? 'off' : 'active',
				),
				$old
			);
			if ( is_wp_error( $s ) ) {
				$fail( $s, $old ? $old : 'new' );
			}
		} elseif ( 'import' === $do ) {
			$text = (string) ( $post['md'] ?? '' );
			if ( ! empty( $_FILES['file']['tmp_name'] ) && is_uploaded_file( $_FILES['file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidationSanitization
				$text = (string) file_get_contents( $_FILES['file']['tmp_name'], false, null, 0, 200000 ); // phpcs:ignore
			}
			$data = flxai_skill_from_md( $text );
			if ( is_wp_error( $data ) ) {
				$fail( $data, 'import' );
			}
			$prev = flxai_skill_get( $data['slug'] );
			$s    = flxai_skill_save( $data, $prev ? $prev['slug'] : '' );
			if ( is_wp_error( $s ) ) {
				$fail( $s, 'import' );
			}
			$msg = 'imported';
		} elseif ( $old && ( $s = flxai_skill_get( $old ) ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
			if ( 'toggle' === $do ) {
				$r = flxai_skill_save( array( 'status' => 'active' === $s['status'] ? 'off' : 'active' ), $old );
				if ( is_wp_error( $r ) ) {
					$fail( $r, '' );
				}
			} elseif ( 'delete' === $do ) {
				flxai_skill_delete( $old );
				$msg = $s['bundled'] ? 'reset' : 'deleted';
			}
		}
		wp_safe_redirect( add_query_arg( 'msg', $msg, $back ) );
		exit;
	}
);

function flxai_page_skills() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
	flxai_page_head( __( 'Skills', 'flx-ai' ), __( 'Named instruction packs the assistant follows: a daily briefing, a donor review, an ad draft. Chats and scheduled tasks use them by name; edit a skill once and every task that uses it follows the new version.', 'flx-ai' ) );
	$err = get_transient( 'flxai_skills_err_' . get_current_user_id() );
	if ( $err && 'error' === ( $_GET['msg'] ?? '' ) ) {
		delete_transient( 'flxai_skills_err_' . get_current_user_id() );
		echo '<div class="notice notice-error"><p>' . esc_html( $err ) . '</p></div>';
	} elseif ( isset( $_GET['msg'] ) ) {
		$m = array(
			'saved'    => __( 'Saved.', 'flx-ai' ),
			'imported' => __( 'Skill imported.', 'flx-ai' ),
			'deleted'  => __( 'Skill deleted.', 'flx-ai' ),
			'reset'    => __( 'Skill reset to the version its plugin ships.', 'flx-ai' ),
		)[ sanitize_key( $_GET['msg'] ) ] ?? '';
		if ( $m ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
	// phpcs:enable
	if ( 'import' === $edit ) {
		flxai_skill_import_form();
		echo '</div>';
		return;
	}
	if ( '' !== $edit ) {
		flxai_skill_form( 'new' === $edit ? null : flxai_skill_get( $edit ) );
		echo '</div>';
		return;
	}

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
	echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills&edit=import' ) ) . '">' . esc_html__( 'Import SKILL.md', 'flx-ai' ) . '</a> ';
	echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills&edit=new' ) ) . '">' . esc_html__( 'New skill', 'flx-ai' ) . '</a>';
	echo '</div></div>';
	echo '<div class="flxai-skill-grid">';
	foreach ( $skills as $s ) {
		$n = (int) ( $used[ $s['slug'] ] ?? 0 );
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
		} elseif ( $s['changed'] ) {
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
	echo '<p class="flxai-note flxai-tasks-tip">' . esc_html__( 'Tip: start a chat message with /name to use a skill, e.g. "/gunluk-brifing only donations". Plugins can ship ready skills with the flxai_skills filter.', 'flx-ai' ) . '</p>';
	echo '</section></div>';
}

/** Ekle / düzenle. */
function flxai_skill_form( $s ) {
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
	echo '<tr><th><label for="flxai-skill-name">' . esc_html__( 'Name', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-name" name="name" type="text" class="regular-text" maxlength="190" required value="' . esc_attr( $s ? $s['name'] : '' ) . '" placeholder="' . esc_attr__( 'Donor review', 'flx-ai' ) . '"></td></tr>';
	echo '<tr><th><label for="flxai-skill-slug">' . esc_html__( 'Short name', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-slug" name="slug" type="text" class="regular-text code" maxlength="60" pattern="[a-z0-9-]*" value="' . esc_attr( $s ? $s['slug'] : '' ) . '" placeholder="bagisci-incelemesi"' . ( $s && $s['bundled'] ? ' readonly' : '' ) . '>';
	echo '<p class="description">' . esc_html__( 'Lowercase letters, numbers and dashes. Used in chat as /short-name and by tasks. Left empty, it is made from the name.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-desc">' . esc_html__( 'Description', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-desc" name="description" type="text" class="large-text" maxlength="500" value="' . esc_attr( $s ? $s['description'] : '' ) . '">';
	echo '<p class="description">' . esc_html__( 'One sentence on when to use it. The assistant picks skills by this.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-body">' . esc_html__( 'Instructions', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-body" name="body" rows="14" class="large-text code" required>' . esc_textarea( $s ? $s['body'] : '' ) . '</textarea>';
	echo '<p class="description">' . esc_html__( 'Markdown. Step by step: what to look at, which tools to use, how the result should look.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-tools">' . esc_html__( 'Suggested tools', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-tools" name="tools" type="text" class="large-text code" list="flxai-tool-names" value="' . esc_attr( $s ? implode( ', ', $s['tools'] ) : '' ) . '">';
	echo '<datalist id="flxai-tool-names">';
	foreach ( $names as $n ) {
		echo '<option value="' . esc_attr( $n ) . '">';
	}
	echo '</datalist><p class="description">' . esc_html__( 'Comma separated tool names (see Tools). A hint for the assistant, not a restriction.', 'flx-ai' ) . '</p></td></tr>';
	echo '<tr><th><label for="flxai-skill-example">' . esc_html__( 'Example output', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-example" name="example" rows="6" class="large-text code">' . esc_textarea( $s ? $s['example'] : '' ) . '</textarea></td></tr>';
	echo '<tr><th>' . esc_html__( 'Status', 'flx-ai' ) . '</th><td><label class="flxai-check"><input type="checkbox" name="active" value="1"' . checked( ! $s || 'active' === $s['status'], true, false ) . '> ' . esc_html__( 'Active', 'flx-ai' ) . '</label></td></tr>';
	echo '</table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills' ) ) . '">' . esc_html__( 'Cancel', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}

function flxai_skill_import_form() {
	echo '<form method="post" enctype="multipart/form-data" class="flxai-card flxai-task-form">';
	wp_nonce_field( 'flxai_skills', 'flxai_skills_nonce' );
	echo '<input type="hidden" name="do" value="import">';
	echo '<h2>' . esc_html__( 'Import SKILL.md', 'flx-ai' ) . '</h2>';
	echo '<p class="flxai-note">' . esc_html__( 'Upload a SKILL.md file or paste its content. A skill with the same name is updated.', 'flx-ai' ) . '</p>';
	echo '<table class="form-table" role="presentation">';
	echo '<tr><th><label for="flxai-skill-file">' . esc_html__( 'File', 'flx-ai' ) . '</label></th><td><input id="flxai-skill-file" name="file" type="file" accept=".md,text/markdown,text/plain"></td></tr>';
	echo '<tr><th><label for="flxai-skill-md">' . esc_html__( 'or paste', 'flx-ai' ) . '</label></th><td><textarea id="flxai-skill-md" name="md" rows="14" class="large-text code" placeholder="---&#10;name: bagisci-incelemesi&#10;description: …&#10;---&#10;&#10;# …"></textarea></td></tr>';
	echo '</table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Import', 'flx-ai' ) . '</button> <a class="button" href="' . esc_url( admin_url( 'admin.php?page=flx-ai-skills' ) ) . '">' . esc_html__( 'Cancel', 'flx-ai' ) . '</a></p>';
	echo '</form>';
}
