<?php
/********************************************************************************
* Subs-ImportantTopics.php - Subs of the Important Topics mod
*********************************************************************************
* This program is distributed in the hope that it is and will be useful, but
* WITHOUT ANY WARRANTIES; without even any implied warranty of MERCHANTABILITY
* or FITNESS FOR A PARTICULAR PURPOSE,
**********************************************************************************/
if (!defined('SMF'))
	die('Hacking attempt...');

/********************************************************************************
* All of our hook functions that we need to pull this off:
********************************************************************************/
function ITM_Load()
{
	add_integration_function('integrate_menu_buttons', 'ITM_menu_buttons', false);
}	

function ITM_menu_buttons(&$buttons)
{
	global $txt, $scripturl, $modSettings, $context;
	
	// Save the top menu area names into a $context variable:
	$context['ITM_labels'] = array();
	foreach ($buttons as $id => $area)
		$context['ITM_labels'][$id] = sprintf($txt['itm_menu_under_level'], $area['title']);

	// Decide where we are going to put this bugger:
	if (!empty($modSettings['itm_menu_home']) && isset($buttons[$modSettings['itm_menu_home']]))
		$root = &$buttons[$modSettings['itm_menu_home']]['sub_buttons'];
	else
		$root = &$buttons;

	// Place the "Our Import Topics" link at where we decided to put it:
	$root['important'] = array(
		'title' => $txt['itm_important_topics'],
		'href' => $scripturl . '?action=important;' . $context['session_var'] . '=' . $context['session_id'],
		'show' => allowedTo('mark_important'),
	);
}

function ITM_actions(&$actions)
{
	$actions['important'] = array('Subs-ImportantTopics.php', 'ITM_Important_Topics');
}

function ITM_mod_button(&$buttons)
{
	global $scripturl, $context, $topicinfo;
	$context['mark_important'] = allowedTo('mark_important');
	if (empty($topicinfo['important']))
		$buttons['important'] = array('test' => 'mark_important', 'text' => 'itm_mark_as_important', 'lang' => true, 'url' => $scripturl . '?action=important;sa=mark;topic=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id']);
	else
		$buttons['important'] = array('test' => 'mark_important', 'text' => 'itm_unmark_as_important', 'lang' => true, 'url' => $scripturl . '?action=important;sa=clear;topic=' . $context['current_topic'] . ';' . $context['session_var'] . '=' . $context['session_id']);
}

function ITM_permissions(&$permissionGroups, &$permissionList, &$leftPermissionGroups, &$hiddenPermissions, &$relabelPermissions)
{
	global $context;
	$permissionList['membergroup']['view_important'] = array(false, 'general', 'moderate_general');
	$permissionList['membergroup']['mark_important'] = array(false, 'general', 'moderate_general');
	if (!allowedTo('mark_important'))
		$context['illegal_permissions'][] = 'mark_important';
	if (!allowedTo('view_important'))
		$context['illegal_permissions'][] = 'view_important';
	$context['non_guest_permissions'][] = 'mark_important';
	$context['non_guest_permissions'][] = 'view_important';
}

function ITM_settings(&$config_vars)
{
	global $txt;

	// Add a temporary hook and the configuration entry:
	add_integration_function('integrate_buffer', 'ITM_Buffer', false);
	$config_vars[] = array('select', 'itm_menu_home', array($txt['itm_menu_top_level']));
}

function ITM_Buffer($buffer)
{
	global $txt, $context, $modSettings;

	// Let's alter the buffer so that we see the "top menu" categories:
	$part1 = substr($buffer, 0, $pos1 = strpos($buffer, 'name="itm_menu_home"'));
	$part3 = substr($buffer, $pos1);
	$part2 = substr($part3, 0, $pos3 = strpos($part3, '</option>') + 9);
	$part3 = substr($part3, $pos3);
	$part1 .= $part2;
	$part2 = substr($part2, strpos($part2, '>') + 1);
	$part2 = substr($part2, 0, strpos($part2, '<option'));
	if (!($place = !empty($modSettings['itm_menu_home']) ? $modSettings['itm_menu_home'] : false))
		$part1 = str_replace('<option value="0" selected="selected">', '<option value="0">', $part1);
	foreach ($context['ITM_labels'] as $id => $label)
	{
		if ($id == 'login' || $id == 'register' || $id == 'logout')
			continue;
		$part1 .= $part2 . '<option value="' . $id . '"' . ($place == $id ? ' selected="selected"' : ''). '>' . $label . '</option>';
	}
	return $part1 . $part3;
}

/********************************************************************************
* The functions necessary to list & mark our "important topics" to the user:
********************************************************************************/
function ITM_Important_Topics()
{
	global $context, $txt, $scripturl, $modSettings, $smcFunc, $sourcedir;

	// If we can't mark topics as important, can we at least view the topic list?
	$cannot_mark = !allowedTo('mark_important');
	if ($cannot_mark)
		isAllowedTo('view_important');

	// Let's check the URL parameters passed before going further:
	if (isset($_GET['sa']) && $_GET['sa'] == 'mark' && isset($_GET['topic']))
	{
		checkSession('get');
		$_GET['topic'] = (int) $_GET['topic'];
		ITM_Mark_Topic($_GET['topic'], true);
		redirectExit('topic=' . $_GET['topic'] . '.0');
	}
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'clear' && isset($_GET['topic']))
	{
		checkSession('get');
		$_GET['topic'] = (int) $_GET['topic'];
		ITM_Mark_Topic($_GET['topic'], false);
		redirectExit('topic=' . $_GET['topic'] . '.0');
	}
	elseif (isset($_GET['sa']) && $_GET['sa'] == 'remove' && isset($_POST['remove']))
	{
		checkSession('post');
		ITM_Mark_Topic($_POST['remove'], false);
		redirectExit('action=important');
	}

	// Set the options for the list component.
	$topic_listOptions = array(
		'id' => 'important_topics',
		'title' => $txt['itm_important_topics'],
		'items_per_page' => $modSettings['defaultMaxMessages'],
		'base_href' => $scripturl . '?action=important' . ';' . $context['session_var'] . '=' . $context['session_id'],
		'default_sort_col' => 'lastpost',
		'no_items_label' => $txt['itm_no_important_topics'],
		'get_items' => array(
			'function' => 'ITM_Get_Topics',
		),
		'get_count' => array(
			'function' => 'ITM_Topics_Count',
		),
		'columns' => array(
			'subject' => array(
				'header' => array(
					'value' => $txt['topics'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl, $txt;
						$board = \'<strong><a href="\' . $scripturl . \'?board=\' . $rowData["id_board"] . \'.0">\' . $rowData[\'board_name\'] . \'</a></strong>\';
						$topic = \'<strong><a href="\' . $scripturl . \'?topic=\' . $rowData["id_topic"] . \'.0">\' . $rowData[\'first_subject\'] . \'</a></strong>\';
						$user = \'<strong><a href="\' . $scripturl . \'?action=home;user=\' . $rowData["first_member"] . \'">\' . $rowData[\'first_poster\'] . \'</a></strong>\';
						return $board . " \\\\ " . $topic . \'<div class="smalltext">\' . $txt["started_by"] . " " . $user . \'</div>\';
					'),
				),
				'sort' => array(
					'default' => 'b.name, mf.subject',
					'reverse' => 'b.name DESC, mf.subject DESC',
				),
			),
			'replies' => array(
				'header' => array(
					'value' => $txt['replies'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return comma_format($rowData[\'num_replies\']);
					'),
					'style' => 'text-align: center; width: 7%',
				),
				'sort' => array(
					'default' => 't.num_replies',
					'reverse' => 't.num_replies DESC',
				),
			),
			'views' => array(
				'header' => array(
					'value' => $txt['views'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						return comma_format($rowData[\'num_views\']);
					'),
					'style' => 'text-align: center; width: 7%',
				),
				'sort' => array(
					'default' => 't.num_views',
					'reverse' => 't.num_views DESC',
				),
			),
			'lastpost' => array(
				'header' => array(
					'value' => $txt['last_post'],
				),
				'data' => array(
					'function' => create_function('$rowData', '
						global $scripturl, $txt;
						$user = \'<strong><a href=\"\' . $scripturl . \'?action=home;user=\' . $rowData["last_member"] . \'">\' . $rowData[\'last_poster\'] . \'</a></strong>\';
						return "<strong>" . $txt["last_post"] . "</strong> " . $txt["by"] . " " . $user . \'<div class="smalltext">\' . timeformat($rowData[\'last_posted\']);
					'),
					'style' => 'width: 30%',
				),
				'sort' => array(
					'default' => 'ml.poster_time',
					'reverse' => 'ml.poster_time DESC',
				),
			),
			'check' => array(
				'header' => array(
					'value' => '<input type="checkbox" onclick="invertAll(this, this.form);" class="input_check" />',
				),
				'data' => array(
					'sprintf' => array(
						'format' => '<input type="checkbox" name="remove[%1$d]" class="input_check" />',
						'params' => array(
							'id_topic' => false,
						),
					),
					'style' => 'text-align: center; width: 30px',
				),
			),
		),
		'form' => array(
			'href' => $scripturl . '?action=important' . (!$cannot_mark ? ';sa=remove;' : '') . $context['session_var'] . '=' . $context['session_id'],
			'include_sort' => true,
			'include_start' => true,
		),
		'additional_rows' => array(
			array(
				'position' => 'below_table_data',
				'value' => '<input type="submit" name="remove_submit" class="button_submit" value="' . $txt['itm_unmark_as_important'] . '" onclick="return confirm(\'' . $txt['itm_unmark_confirm'] . '\');" />',
				'style' => 'text-align: right;',
			),
		),
	);

	// If we can't mark topics, then remove the ability from the form:
	if ($cannot_mark)
		unset($topic_listOptions['columns']['check'], $topic_listOptions['additional_rows']);

	// Create the list.
	$context['page_title' ] = $txt['itm_important_topics'];
	$context['sub_template'] = 'important_topics';
	require_once($sourcedir . '/Subs-List.php');
	createList($topic_listOptions);
}

function ITM_Topics_Count()
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', '
		SELECT COUNT(t.important) AS count
		FROM {db_prefix}topics AS t
			LEFT JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
		WHERE {query_see_board}
			AND important = {int:marked_important}',
		array(
			'marked_important' => 1,
		)
	);
	list($count) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);
	return $count;
}

function ITM_Get_Topics($start, $items_per_page, $sort)
{
	global $smcFunc;
	$request = $smcFunc['db_query']('', '
		SELECT
			t.id_topic, t.num_replies, t.num_views, t.id_first_msg, b.id_board, b.name AS board_name,
			mf.id_member AS first_member, COALESCE(meml.real_name, ml.poster_name) AS last_poster, 
			ml.id_member AS last_member, COALESCE(memf.real_name, mf.poster_name) AS first_poster, 
			mf.subject AS first_subject, mf.poster_time AS first_posted,
			ml.subject AS last_subject, ml.poster_time AS last_posted
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}boards AS b ON (b.id_board = t.id_board)
			INNER JOIN {db_prefix}messages AS mf ON (mf.id_msg = t.id_first_msg)
			INNER JOIN {db_prefix}messages AS ml ON (ml.id_msg = t.id_last_msg)
			LEFT JOIN {db_prefix}members AS memf ON (memf.id_member = mf.id_member)
			LEFT JOIN {db_prefix}members AS meml ON (meml.id_member = ml.id_member)
		WHERE {query_see_board}
			AND t.important = {int:marked_important}
		ORDER BY {raw:sort}
		LIMIT {int:start}, {int:per_page}',
		array(
			'sort' => $sort,
			'start' => $start,
			'per_page' => $items_per_page,
			'marked_important' => 1,
		)
	);
	$topics = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$topics[] = $row;
	$smcFunc['db_free_result']($request);
	return $topics;
}

function ITM_Mark_Topic($topics, $important = 0)
{
	global $smcFunc;
	checkSession('get');
	isAllowedTo('mark_important');
	
	// Let's create an array with our sanitized topic list:
	$topic_list = array();
	if (!is_array($topics))
		$topic_list = array((int) $topics);
	else
		$topic_list = array_keys($topics);
			
	// Update all of the topics with their new "important" status:
	if (!empty($topic_list))
	{
		$smcFunc['db_query']('', '
			UPDATE {db_prefix}topics
			SET important = {int:important}
			WHERE id_topic IN ({array_int:id_topic})',
			array(
				'important' => (int) $important,
				'id_topic' => $topic_list,
			)
		);
	}
}

/********************************************************************************
* Our stupid, short template function: a necessary evil....
********************************************************************************/
function template_important_topics()
{
	template_show_list('important_topics');
}

/********************************************************************************
* Our "Random Important Topic" SSI function:
********************************************************************************/
function ssi_getRandomImportantTopic($board = null, $length = null, $output_method = 'echo')
{
	global $scripturl, $db_prefix, $txt, $settings, $modSettings, $context;
	global $smcFunc;

	loadLanguage('Stats');

	if ($board !== null)
		$board = (int) $board;
	elseif (isset($_GET['board']))
		$board = (int) $_GET['board'];

	if ($length === null)
		$length = isset($_GET['length']) ? (int) $_GET['length'] : 0;
	else
		$length = (int) $length;

	// Make sure guests can see this board.
	$request = $smcFunc['db_query']('', '
		SELECT id_board
		FROM {db_prefix}boards
		WHERE ' . ($board === null ? '' : 'id_board = {int:current_board}
			AND ') . 'FIND_IN_SET(-1, member_groups) != 0
		LIMIT 1',
		array(
			'current_board' => $board,
		)
	);
	if ($smcFunc['db_num_rows']($request) == 0)
	{
		if ($output_method == 'echo')
			die($txt['ssi_no_guests']);
		else
			return array();
	}
	list ($board) = $smcFunc['db_fetch_row']($request);
	$smcFunc['db_free_result']($request);

	// Load the message icons - the usual suspects.
	$stable_icons = array('xx', 'thumbup', 'thumbdown', 'exclamation', 'question', 'lamp', 'smiley', 'angry', 'cheesy', 'grin', 'sad', 'wink', 'moved', 'recycled', 'wireless');
	$icon_sources = array();
	foreach ($stable_icons as $icon)
		$icon_sources[$icon] = 'images_url';

	// Find the post ids.
	$request = $smcFunc['db_query']('', '
		SELECT t.id_first_msg
		FROM {db_prefix}topics as t
		LEFT JOIN {db_prefix}boards as b ON (b.id_board = t.id_board)
		WHERE t.id_board = {int:current_board}' . ($modSettings['postmod_active'] ? '
			AND t.approved = {int:is_approved}' : '') . '
			AND t.important = {int:is_important}
			AND {query_see_board}
		ORDER BY t.id_first_msg DESC',
		array(
			'current_board' => $board,
			'is_important' => 1,
			'is_approved' => 1,
		)
	);
	$posts = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$posts[] = $row['id_first_msg'];
	$smcFunc['db_free_result']($request);

	if (empty($posts))
		return array();

	// Find the posts.
	$request = $smcFunc['db_query']('', '
		SELECT
			m.icon, m.subject, m.body, IFNULL(mem.real_name, m.poster_name) AS poster_name, m.poster_time,
			t.num_replies, t.id_topic, m.id_member, m.smileys_enabled, m.id_msg, t.locked, t.id_last_msg
		FROM {db_prefix}topics AS t
			INNER JOIN {db_prefix}messages AS m ON (m.id_msg = t.id_first_msg)
			LEFT JOIN {db_prefix}members AS mem ON (mem.id_member = m.id_member)
		WHERE t.id_first_msg IN ({array_int:post_list})
		ORDER BY t.id_first_msg DESC
		LIMIT ' . count($posts),
		array(
			'post_list' => $posts,
		)
	);
	$return = array();
	while ($row = $smcFunc['db_fetch_assoc']($request))
	{
		// If we want to limit the length of the post.
		if (!empty($length) && $smcFunc['strlen']($row['body']) > $length)
		{
			$row['body'] = $smcFunc['substr']($row['body'], 0, $length);

			// The first space or line break. (<br />, etc.)
			$cutoff = max(strrpos($row['body'], ' '), strrpos($row['body'], '<'));

			if ($cutoff !== false)
				$row['body'] = $smcFunc['substr']($row['body'], 0, $cutoff);
			$row['body'] .= '...';
		}

		$row['body'] = parse_bbc($row['body'], $row['smileys_enabled'], $row['id_msg']);

		// Check that this message icon is there...
		if (empty($modSettings['messageIconChecks_disable']) && !isset($icon_sources[$row['icon']]))
			$icon_sources[$row['icon']] = file_exists($settings['theme_dir'] . '/images/post/' . $row['icon'] . '.gif') ? 'images_url' : 'default_images_url';

		censorText($row['subject']);
		censorText($row['body']);

		$return[] = array(
			'id' => $row['id_topic'],
			'message_id' => $row['id_msg'],
			'icon' => '<img src="' . $settings[$icon_sources[$row['icon']]] . '/post/' . $row['icon'] . '.gif" alt="' . $row['icon'] . '" />',
			'subject' => $row['subject'],
			'time' => timeformat($row['poster_time']),
			'timestamp' => forum_time(true, $row['poster_time']),
			'body' => $row['body'],
			'href' => $scripturl . '?topic=' . $row['id_topic'] . '.0',
			'link' => '<a href="' . $scripturl . '?topic=' . $row['id_topic'] . '.0">' . $row['num_replies'] . ' ' . ($row['num_replies'] == 1 ? $txt['ssi_comment'] : $txt['ssi_comments']) . '</a>',
			'replies' => $row['num_replies'],
			'comment_href' => !empty($row['locked']) ? '' : $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'],
			'comment_link' => !empty($row['locked']) ? '' : '<a href="' . $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . ';last_msg=' . $row['id_last_msg'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'new_comment' => !empty($row['locked']) ? '' : '<a href="' . $scripturl . '?action=post;topic=' . $row['id_topic'] . '.' . $row['num_replies'] . '">' . $txt['ssi_write_comment'] . '</a>',
			'poster' => array(
				'id' => $row['id_member'],
				'name' => $row['poster_name'],
				'href' => !empty($row['id_member']) ? $scripturl . '?action=profile;u=' . $row['id_member'] : '',
				'link' => !empty($row['id_member']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['id_member'] . '">' . $row['poster_name'] . '</a>' : $row['poster_name']
			),
			'locked' => !empty($row['locked']),
			'is_last' => false
		);
	}
	$smcFunc['db_free_result']($request);

	if (empty($return))
		return $return;

	$func = function_exists('random_int') ? 'random_int' : 'rand';
	$return = $return[ $func(0, count($return) - 1) ];

	if ($output_method != 'echo')
		return $return;

	echo '
			<div class="news_item">
				<h3 class="news_header">
					', $return['icon'], '
					<a href="', $return['href'], '">', $return['subject'], '</a>
				</h3>
				<div class="news_timestamp">', $return['time'], ' ', $txt['by'], ' ', $return['poster']['link'], '</div>
				<div class="news_body" style="padding: 2ex 0;">', $return['body'], '</div>
				', $return['link'], $return['locked'] ? '' : ' | ' . $return['comment_link'], '
			</div>';
}

?>