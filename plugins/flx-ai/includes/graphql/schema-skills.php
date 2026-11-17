<?php
/**
 * Flux AI GraphQL: skills, their folder files and tests, and the brand kit.
 *
 * @package FLX_AI
 */

defined( 'ABSPATH' ) || exit;

flx_gql_type(
	'FlxAiSkill',
	array(
		'description' => __( 'A skill: an instruction folder the assistant follows (open Agent Skills format).', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Summary::class,
			array(
				'slug'        => array( 'String!', __( 'Folder name; used in chat as /name.', 'flx-ai' ) ),
				'name'        => array( 'String!', __( 'Title.', 'flx-ai' ) ),
				'description' => array( 'String!', __( 'What it does and when to use it.', 'flx-ai' ) ),
				'version'     => array( 'Int!', __( 'Version; grows when the instructions change.', 'flx-ai' ) ),
				'status'      => array( 'String!', __( 'active or off.', 'flx-ai' ) ),
				'bundled'     => array( 'Boolean!', __( 'Shipped by a plugin (not a skill of this site).', 'flx-ai' ) ),
				'source'      => array( 'String!', __( 'The plugin or package that ships it; empty for site skills.', 'flx-ai' ) ),
				'changed'     => array( 'Boolean!', __( 'A shipped skill that was edited on this site.', 'flx-ai' ) ),
				'saved'       => array( 'Boolean!', __( 'The site keeps its own copy (a shipped skill can be reset).', 'flx-ai' ) ),
				'fileCount'   => array( 'Int!', __( 'Files in the folder besides SKILL.md.', 'flx-ai' ), 'file_count' ),
				'hasScripts'  => array( 'Boolean!', __( 'The folder has scripts (stored, not run on this server).', 'flx-ai' ), 'has_scripts' ),
				'tasks'       => array( 'Int!', __( 'Scheduled tasks that use it.', 'flx-ai' ) ),
				'exportUrl'   => array( 'String!', __( 'Download link of the .zip (signed, short lived).', 'flx-ai' ), 'export_url' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillsOverview',
	array(
		'description' => __( 'The skills page.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skills_Overview::class,
			array(
				'skills'   => array( '[FlxAiSkill!]!', __( 'Skills, by title.', 'flx-ai' ) ),
				'restrict' => array( 'Boolean!', __( 'A message that uses a skill offers only the tools of its allowed-tools list.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillFile',
	array(
		'description' => __( 'A file in a skill folder.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_File::class,
			array(
				'path'        => array( 'String!', __( 'Path inside the folder.', 'flx-ai' ) ),
				'size'        => array( 'Int!', __( 'Size in bytes.', 'flx-ai' ) ),
				'downloadUrl' => array( 'String!', __( 'Download link (signed, short lived).', 'flx-ai' ), 'download_url' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillFileContent',
	array(
		'description' => __( 'The content of a skill file.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_File_Content::class,
			array(
				'path'    => array( 'String!', __( 'Path inside the folder.', 'flx-ai' ) ),
				'size'    => array( 'Int!', __( 'Size in bytes.', 'flx-ai' ) ),
				'text'    => array( 'Boolean!', __( 'A text file; false for images, fonts and archives.', 'flx-ai' ) ),
				'content' => array( 'String!', __( 'The text (up to 2 MB); empty for a binary file.', 'flx-ai' ) ),
				'note'    => array( 'String!', __( 'Why a binary file cannot be shown.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillTriggerCase',
	array(
		'description' => __( 'A trigger test prompt.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Trigger_Case::class,
			array(
				'query'         => array( 'String!', __( 'The prompt.', 'flx-ai' ) ),
				'shouldTrigger' => array( 'Boolean!', __( 'The assistant should pick the skill for it.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillEvalCase',
	array(
		'description' => __( 'A functional test.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Eval_Case::class,
			array(
				'id'           => array( 'String!', __( 'Test number.', 'flx-ai' ) ),
				'prompt'       => array( 'String!', __( 'The request.', 'flx-ai' ) ),
				'expectations' => array( '[String!]!', __( 'What a good answer must contain, one check per entry.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillEvals',
	array(
		'description' => __( 'The saved tests of a skill.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Evals::class,
			array(
				'triggers' => array( '[FlxAiSkillTriggerCase!]!', __( 'Trigger test prompts.', 'flx-ai' ) ),
				'cases'    => array( '[FlxAiSkillEvalCase!]!', __( 'Functional tests.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillTriggerRow',
	array(
		'description' => __( 'One prompt of a trigger test run.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Trigger_Row::class,
			array(
				'query'         => array( 'String!', __( 'The prompt.', 'flx-ai' ) ),
				'shouldTrigger' => array( 'Boolean!', __( 'Expected result.', 'flx-ai' ) ),
				'triggered'     => array( 'Boolean!', __( 'The assistant picked the skill.', 'flx-ai' ) ),
				'picked'        => array( '[String!]!', __( 'Skills the assistant picked.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillTriggerResult',
	array(
		'description' => __( 'The last trigger test run.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Trigger_Result::class,
			array(
				'ts'      => array( 'FlxDateTime!', __( 'When it ran.', 'flx-ai' ), 'ts', 'time' ),
				'model'   => array( 'String!', __( 'Model used.', 'flx-ai' ) ),
				'hits'    => array( 'Int!', __( 'Prompts with the expected result.', 'flx-ai' ) ),
				'total'   => array( 'Int!', __( 'Prompts run.', 'flx-ai' ) ),
				'rate'    => array( 'Int!', __( 'Percent correct.', 'flx-ai' ) ),
				'cost'    => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'rows'    => array( '[FlxAiSkillTriggerRow!]!', __( 'One row per prompt.', 'flx-ai' ) ),
				'partial' => array( 'Boolean!', __( 'Stopped early by the time limit.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillSide',
	array(
		'description' => __( 'One side (with or without the skill) of a functional test run.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Side::class,
			array(
				'passed' => array( 'Int!', __( 'Checks passed.', 'flx-ai' ) ),
				'total'  => array( 'Int!', __( 'Checks.', 'flx-ai' ) ),
				'in'     => array( 'Int!', __( 'Input tokens.', 'flx-ai' ), 'in' ),
				'out'    => array( 'Int!', __( 'Output tokens.', 'flx-ai' ) ),
				'cost'   => array( 'Float!', __( 'Cost in USD.', 'flx-ai' ) ),
				'ms'     => array( 'Int!', __( 'Duration in milliseconds.', 'flx-ai' ) ),
				'output' => array( 'String!', __( 'The answer.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillCheck',
	array(
		'description' => __( 'One check of a functional test run.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Check::class,
			array(
				'text'    => array( 'String!', __( 'The check.', 'flx-ai' ) ),
				'with'    => array( 'Boolean!', __( 'Passed with the skill.', 'flx-ai' ) ),
				'without' => array( 'Boolean!', __( 'Passed without it.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillFuncResult',
	array(
		'description' => __( 'The last run of a functional test.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Func_Result::class,
			array(
				'ts'        => array( 'FlxDateTime!', __( 'When it ran.', 'flx-ai' ), 'ts', 'time' ),
				'id'        => array( 'String!', __( 'Test number.', 'flx-ai' ) ),
				'prompt'    => array( 'String!', __( 'The request.', 'flx-ai' ) ),
				'with'      => array( 'FlxAiSkillSide!', __( 'The answer with the skill.', 'flx-ai' ) ),
				'without'   => array( 'FlxAiSkillSide!', __( 'The answer without it.', 'flx-ai' ) ),
				'checks'    => array( '[FlxAiSkillCheck!]!', __( 'Checks.', 'flx-ai' ) ),
				'note'      => array( 'String!', __( 'Judge note.', 'flx-ai' ) ),
				'judgeCost' => array( 'Float!', __( 'Cost of the judging in USD.', 'flx-ai' ), 'judge_cost' ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillResults',
	array(
		'description' => __( 'The last results of a skill\'s tests.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Results::class,
			array(
				'trigger'    => array( 'FlxAiSkillTriggerResult', __( 'Last trigger test; null before the first run.', 'flx-ai' ) ),
				'functional' => array( '[FlxAiSkillFuncResult!]!', __( 'Last run of each functional test.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillDetail',
	array(
		'description' => __( 'A skill with everything its edit screen needs.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Detail::class,
			array(
				'skill'         => array( 'FlxAiSkill!', __( 'The skill.', 'flx-ai' ) ),
				'body'          => array( 'String!', __( 'SKILL.md instructions (Markdown).', 'flx-ai' ) ),
				'tools'         => array( '[String!]!', __( 'allowed-tools: tool names; a trailing * matches a prefix.', 'flx-ai' ) ),
				'license'       => array( 'String!', __( 'License.', 'flx-ai' ) ),
				'compatibility' => array( 'String!', __( 'Compatibility note.', 'flx-ai' ) ),
				'example'       => array( 'String!', __( 'Example output (references/examples.md).', 'flx-ai' ) ),
				'files'         => array( '[FlxAiSkillFile!]!', __( 'Folder files besides SKILL.md.', 'flx-ai' ) ),
				'toolNames'     => array( '[String!]!', __( 'Tool names to suggest in the allowed-tools field.', 'flx-ai' ), 'tool_names' ),
				'evals'         => array( 'FlxAiSkillEvals!', __( 'Saved tests.', 'flx-ai' ) ),
				'results'       => array( 'FlxAiSkillResults!', __( 'Last test results.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiSkillImportResult',
	array(
		'description' => __( 'What an import did.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Skill_Import_Result::class,
			array(
				'imported' => array( '[String!]!', __( 'Skills written.', 'flx-ai' ) ),
				'notes'    => array( '[String!]!', __( 'Things to know, such as skipped files.', 'flx-ai' ) ),
				'overview' => array( 'FlxAiSkillsOverview!', __( 'The skills page after the import.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiBrandDoc',
	array(
		'description' => __( 'A brand kit document (Markdown).', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Brand_Doc::class,
			array(
				'name'    => array( 'String!', __( 'File name, e.g. voice.md.', 'flx-ai' ) ),
				'content' => array( 'String!', __( 'Markdown.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiBrandColor',
	array(
		'description' => __( 'A color of the theme.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Brand_Color::class,
			array(
				'slug'  => array( 'String!', __( 'Palette slug.', 'flx-ai' ) ),
				'name'  => array( 'String!', __( 'Name.', 'flx-ai' ) ),
				'color' => array( 'String!', __( 'Hex color.', 'flx-ai' ) ),
			)
		),
	)
);

flx_gql_type(
	'FlxAiBrand',
	array(
		'description' => __( 'The brand kit design and writing skills read.', 'flx-ai' ),
		'fields'      => flxai_gql_fields(
			FLXAI_Brand::class,
			array(
				'name'           => array( 'String!', __( 'Organisation name; empty uses the site name.', 'flx-ai' ) ),
				'tagline'        => array( 'String!', __( 'Tagline.', 'flx-ai' ) ),
				'website'        => array( 'String!', __( 'Website shown on visuals.', 'flx-ai' ) ),
				'phone'          => array( 'String!', __( 'Phone or WhatsApp.', 'flx-ai' ) ),
				'handle'         => array( 'String!', __( 'Social media handle or hashtag.', 'flx-ai' ) ),
				'colorPrimary'   => array( 'String!', __( 'Primary color (hex).', 'flx-ai' ), 'color_primary' ),
				'colorAccent'    => array( 'String!', __( 'Accent color (hex).', 'flx-ai' ), 'color_accent' ),
				'colorDark'      => array( 'String!', __( 'Dark color (hex).', 'flx-ai' ), 'color_dark' ),
				'colorLight'     => array( 'String!', __( 'Light color (hex).', 'flx-ai' ), 'color_light' ),
				'logoId'         => array( 'Int!', __( 'Logo media ID.', 'flx-ai' ), 'logo_id' ),
				'logoUrl'        => array( 'String!', __( 'Logo URL.', 'flx-ai' ), 'logo_url' ),
				'logoLightId'    => array( 'Int!', __( 'White logo media ID.', 'flx-ai' ), 'logo_light_id' ),
				'logoLightUrl'   => array( 'String!', __( 'White logo URL.', 'flx-ai' ), 'logo_light_url' ),
				'partnerIds'     => array( '[Int!]!', __( 'Partner logo media IDs.', 'flx-ai' ), 'partner_ids' ),
				'partnerUrls'    => array( '[String!]!', __( 'Partner logo URLs.', 'flx-ai' ), 'partner_urls' ),
				'notes'          => array( 'String!', __( 'Design notes.', 'flx-ai' ) ),
				'docs'           => array( '[FlxAiBrandDoc!]!', __( 'Markdown documents.', 'flx-ai' ) ),
				'defaultName'    => array( 'String!', __( 'Name used when the field is empty.', 'flx-ai' ), 'default_name' ),
				'defaultTagline' => array( 'String!', __( 'Tagline used when the field is empty.', 'flx-ai' ), 'default_tagline' ),
				'defaultWebsite' => array( 'String!', __( 'Website used when the field is empty.', 'flx-ai' ), 'default_website' ),
				'themePalette'   => array( '[FlxAiBrandColor!]!', __( 'Colors of the active theme.', 'flx-ai' ), 'theme_palette' ),
			)
		),
	)
);

/** Inputs. */
flx_gql_input(
	'FlxAiSkillTriggerInput',
	array(
		'description' => __( 'A trigger test prompt.', 'flx-ai' ),
		'fields'      => array(
			'query'         => array( 'type' => 'String!', 'description' => __( 'The prompt.', 'flx-ai' ) ),
			'shouldTrigger' => array( 'type' => 'Boolean', 'description' => __( 'The assistant should pick the skill (default true).', 'flx-ai' ) ),
		),
	)
);

flx_gql_input(
	'FlxAiSkillCaseInput',
	array(
		'description' => __( 'A functional test.', 'flx-ai' ),
		'fields'      => array(
			'prompt'       => array( 'type' => 'String!', 'description' => __( 'The request.', 'flx-ai' ) ),
			'expectations' => array( 'type' => '[String!]', 'description' => __( 'Checks, one per entry.', 'flx-ai' ) ),
		),
	)
);

flx_gql_input(
	'FlxAiBrandDocInput',
	array(
		'description' => __( 'A brand kit document; empty content deletes it.', 'flx-ai' ),
		'fields'      => array(
			'name'    => array( 'type' => 'String!', 'description' => __( 'File name, e.g. voice.md.', 'flx-ai' ) ),
			'content' => array( 'type' => 'String!', 'description' => __( 'Markdown.', 'flx-ai' ) ),
		),
	)
);

/** Queries. */
flx_gql_query(
	'flxAiSkills',
	array(
		'description' => __( 'The skills page: every skill, shipped and of this site.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiSkillsOverview!',
		'resolve'     => static function (): FLXAI_Skills_Overview {
			return FLXAI_Skills_Service::overview();
		},
	)
);

flx_gql_query(
	'flxAiSkill',
	array(
		'description' => __( 'One skill with its folder, tests and last test results.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiSkillDetail',
		'args'        => array( 'slug' => array( 'type' => 'String!', 'description' => __( 'Skill folder name.', 'flx-ai' ) ) ),
		'resolve'     => static function ( $root, array $args ) {
			$r = FLXAI_Skills_Service::detail( flx_gql_str( $args['slug'] ?? '' ) );
			return $r instanceof WP_Error ? null : $r;
		},
	)
);

flx_gql_query(
	'flxAiSkillFile',
	array(
		'description' => __( 'Reads a text file of a skill folder.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiSkillFileContent',
		'args'        => array(
			'slug' => array( 'type' => 'String!', 'description' => __( 'Skill folder name.', 'flx-ai' ) ),
			'path' => array( 'type' => 'String!', 'description' => __( 'Path inside the folder.', 'flx-ai' ) ),
		),
		'resolve'     => static function ( $root, array $args ) {
			$r = FLXAI_Skills_Service::file( flx_gql_str( $args['slug'] ?? '' ), flx_gql_str( $args['path'] ?? '' ) );
			return $r instanceof WP_Error ? null : $r;
		},
	)
);

flx_gql_query(
	'flxAiBrand',
	array(
		'description' => __( 'The brand kit.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'type'        => 'FlxAiBrand!',
		'resolve'     => static function (): FLXAI_Brand {
			return FLXAI_Skills_Service::brand();
		},
	)
);

/** Mutations. */
$flxai_skill_out = array( 'skill' => array( 'type' => 'FlxAiSkillDetail', 'description' => __( 'The skill after the change; null when the input was refused.', 'flx-ai' ) ) );
$flxai_slug_in   = array( 'slug' => array( 'type' => 'String!', 'description' => __( 'Skill folder name.', 'flx-ai' ) ) );

flx_gql_mutation(
	'flxAiSkillSave',
	array(
		'description' => __( 'Creates a skill, or changes one when `current` is given. Fields you leave out keep their value.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'current'       => array( 'type' => 'String', 'description' => __( 'Folder name of the skill to change; leave out to create one.', 'flx-ai' ) ),
			'slug'          => array( 'type' => 'String', 'description' => __( 'New folder name (lowercase letters, numbers, dashes); empty takes it from the title.', 'flx-ai' ) ),
			'name'          => array( 'type' => 'String', 'description' => __( 'Title.', 'flx-ai' ) ),
			'description'   => array( 'type' => 'String', 'description' => __( 'What it does and when to use it (up to 1024 characters).', 'flx-ai' ) ),
			'body'          => array( 'type' => 'String', 'description' => __( 'SKILL.md instructions (Markdown).', 'flx-ai' ) ),
			'tools'         => array( 'type' => '[String!]', 'description' => __( 'allowed-tools.', 'flx-ai' ) ),
			'license'       => array( 'type' => 'String', 'description' => __( 'License.', 'flx-ai' ) ),
			'compatibility' => array( 'type' => 'String', 'description' => __( 'Compatibility note.', 'flx-ai' ) ),
			'example'       => array( 'type' => 'String', 'description' => __( 'Example output.', 'flx-ai' ) ),
			'active'        => array( 'type' => 'Boolean', 'description' => __( 'The skill is on (false turns it off).', 'flx-ai' ) ),
		),
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$current = flx_gql_str( $input['current'] ?? '' );
			unset( $input['current'] );
			$r = FLXAI_Skills_Service::save( $input, $current );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillToggle',
	array(
		'description' => __( 'Turns a skill on or off.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in,
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::toggle( flx_gql_str( $input['slug'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillDelete',
	array(
		'description' => __( 'Deletes a skill of this site, or resets a shipped skill to the version its plugin ships.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in,
		'output'      => array( 'deleted' => array( 'type' => 'Boolean', 'description' => __( 'Done.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::delete( flx_gql_str( $input['slug'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'deleted' => true );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillsSettings',
	array(
		'description' => __( 'Sets whether a message that uses a skill offers only the tools of its allowed-tools list.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array( 'restrict' => array( 'type' => 'Boolean!', 'description' => __( 'Offer only the allowed tools.', 'flx-ai' ) ) ),
		'output'      => array( 'overview' => array( 'type' => 'FlxAiSkillsOverview', 'description' => __( 'The skills page.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			return array( 'overview' => FLXAI_Skills_Service::set_restrict( ! empty( $input['restrict'] ) ) );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillImport',
	array(
		'description' => __( 'Imports skills from a .zip, .skill or SKILL.md file (base64) or pasted SKILL.md text. A skill with the same name is updated.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'markdown'      => array( 'type' => 'String', 'description' => __( 'Pasted SKILL.md.', 'flx-ai' ) ),
			'contentBase64' => array( 'type' => 'String', 'description' => __( 'File content, base64 (up to 20 MB).', 'flx-ai' ) ),
			'fileName'      => array( 'type' => 'String', 'description' => __( 'File name.', 'flx-ai' ) ),
		),
		'output'      => array( 'result' => array( 'type' => 'FlxAiSkillImportResult', 'description' => __( 'What was imported; null when refused.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::import( flx_gql_str( $input['markdown'] ?? '' ), flx_gql_str( $input['contentBase64'] ?? '' ), flx_gql_str( $input['fileName'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'result' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillFileSave',
	array(
		'description' => __( 'Writes a text file into a skill folder. A shipped skill is copied to the site first.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in + array(
			'path'    => array( 'type' => 'String!', 'description' => __( 'Path, e.g. references/guide.md.', 'flx-ai' ) ),
			'content' => array( 'type' => 'String!', 'description' => __( 'Text.', 'flx-ai' ) ),
		),
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::file_save( flx_gql_str( $input['slug'] ?? '' ), flx_gql_str( $input['path'] ?? '' ), flx_gql_str( $input['content'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillFileDelete',
	array(
		'description' => __( 'Deletes a file of a skill folder.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in + array( 'path' => array( 'type' => 'String!', 'description' => __( 'Path inside the folder.', 'flx-ai' ) ) ),
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::file_delete( flx_gql_str( $input['slug'] ?? '' ), flx_gql_str( $input['path'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillFileUpload',
	array(
		'description' => __( 'Uploads a file (base64, up to 5 MB) into a sub folder of a skill folder.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in + array(
			'folder'        => array( 'type' => 'String', 'description' => __( 'references, assets, scripts or evals (default references).', 'flx-ai' ) ),
			'fileName'      => array( 'type' => 'String!', 'description' => __( 'File name.', 'flx-ai' ) ),
			'contentBase64' => array( 'type' => 'String!', 'description' => __( 'File content, base64.', 'flx-ai' ) ),
		),
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$folder = flx_gql_str( $input['folder'] ?? '' );
			$r      = FLXAI_Skills_Service::file_upload( flx_gql_str( $input['slug'] ?? '' ), '' === $folder ? 'references' : $folder, flx_gql_str( $input['fileName'] ?? '' ), flx_gql_str( $input['contentBase64'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillEvalsSave',
	array(
		'description' => __( 'Saves the trigger prompts and functional tests of a skill (they replace the old ones).', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in + array(
			'triggers' => array( 'type' => '[FlxAiSkillTriggerInput!]', 'description' => __( 'Trigger test prompts (up to 40).', 'flx-ai' ) ),
			'cases'    => array( 'type' => '[FlxAiSkillCaseInput!]', 'description' => __( 'Functional tests (up to 10).', 'flx-ai' ) ),
		),
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$trig  = array();
			$cases = array();
			foreach ( flx_gql_arr( $input['triggers'] ?? array() ) as $t ) {
				$trig[] = flx_gql_arr( $t );
			}
			foreach ( flx_gql_arr( $input['cases'] ?? array() ) as $c ) {
				$cases[] = flx_gql_arr( $c );
			}
			$r = FLXAI_Skills_Service::evals_save( flx_gql_str( $input['slug'] ?? '' ), $trig, $cases );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiSkillTestRun',
	array(
		'description' => __( 'Runs the trigger test or one functional test. This calls the chat model and is billed like a short chat (purpose skill_test).', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => $flxai_slug_in + array(
			'kind' => array( 'type' => 'String!', 'description' => __( 'trigger or functional.', 'flx-ai' ) ),
			'id'   => array( 'type' => 'String', 'description' => __( 'Functional test number.', 'flx-ai' ) ),
		),
		'output'      => $flxai_skill_out,
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::test_run( flx_gql_str( $input['slug'] ?? '' ), flx_gql_str( $input['kind'] ?? '' ), flx_gql_str( $input['id'] ?? '' ) );
			return $r instanceof WP_Error ? $r : array( 'skill' => $r );
		},
	)
);

flx_gql_mutation(
	'flxAiBrandSave',
	array(
		'description' => __( 'Saves the brand kit. Fields you leave out keep their value; a document with empty content is deleted.', 'flx-ai' ),
		'cap'         => 'manage_options',
		'input'       => array(
			'name'         => array( 'type' => 'String', 'description' => __( 'Organisation name.', 'flx-ai' ) ),
			'tagline'      => array( 'type' => 'String', 'description' => __( 'Tagline.', 'flx-ai' ) ),
			'website'      => array( 'type' => 'String', 'description' => __( 'Website shown on visuals.', 'flx-ai' ) ),
			'phone'        => array( 'type' => 'String', 'description' => __( 'Phone or WhatsApp.', 'flx-ai' ) ),
			'handle'       => array( 'type' => 'String', 'description' => __( 'Social media handle or hashtag.', 'flx-ai' ) ),
			'colorPrimary' => array( 'type' => 'String', 'description' => __( 'Primary color, #rrggbb.', 'flx-ai' ) ),
			'colorAccent'  => array( 'type' => 'String', 'description' => __( 'Accent color, #rrggbb.', 'flx-ai' ) ),
			'colorDark'    => array( 'type' => 'String', 'description' => __( 'Dark color, #rrggbb.', 'flx-ai' ) ),
			'colorLight'   => array( 'type' => 'String', 'description' => __( 'Light color, #rrggbb.', 'flx-ai' ) ),
			'logoId'       => array( 'type' => 'Int', 'description' => __( 'Logo media ID.', 'flx-ai' ) ),
			'logoLightId'  => array( 'type' => 'Int', 'description' => __( 'White logo media ID.', 'flx-ai' ) ),
			'partnerIds'   => array( 'type' => '[Int!]', 'description' => __( 'Partner logo media IDs (up to 6).', 'flx-ai' ) ),
			'notes'        => array( 'type' => 'String', 'description' => __( 'Design notes.', 'flx-ai' ) ),
			'docs'         => array( 'type' => '[FlxAiBrandDocInput!]', 'description' => __( 'Documents to write; empty content deletes one.', 'flx-ai' ) ),
		),
		'output'      => array( 'brand' => array( 'type' => 'FlxAiBrand', 'description' => __( 'The brand kit after saving; null when refused.', 'flx-ai' ) ) ),
		'resolve'     => static function ( array $input ) {
			$r = FLXAI_Skills_Service::brand_save( $input );
			return $r instanceof WP_Error ? $r : array( 'brand' => $r );
		},
	)
);
