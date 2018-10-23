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
function ITM_menu_buttons(&$buttons)
{
	global $txt, $scripturl, $context;
	$buttons['home']['sub_buttons']['important'] = array(
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
	$permissionList['membergroup']['mark_important'] = array(false, 'maintenance', 'moderate_general');
	if (!allowedTo('mark_important'))
		$context['illegal_permissions'][] = 'mark_important';
	$context['non_guest_permissions'][] = 'mark_important';
}

/********************************************************************************
* The functions necessary to list & mark our "important topics" to the user:
********************************************************************************/
function ITM_Important_Topics()
{
	global $context, $txt, $scripturl, $modSettings, $smcFunc, $sourcedir;

	// Set up for listing the "important" topics:
	isAllowedTo('mark_important');
	$context['page_title' ] = $txt['itm_important_topics'];
	$context['sub_template'] = 'important_topics';

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
			'href' => $scripturl . '?action=important;sa=remove;' . $context['session_var'] . '=' . $context['session_id'],
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

	// Create the list.
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
			mf.id_member AS first_member, IFNULL(meml.real_name, ml.poster_name) AS last_poster, 
			ml.id_member AS last_member, IFNULL(memf.real_name, mf.poster_name) AS first_poster, 
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

?>