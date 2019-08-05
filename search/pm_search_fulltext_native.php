<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

namespace anavaro\pmsearch\search;
/**
* phpBB's own db driven fulltext search, version 2
*/
class pm_search_fulltext_native extends \phpbb\search\fulltext_native
{
	protected $target;

	/**
	* Returns the name of this search backend to be displayed to administrators
	*
	* @return string Name
	*/
	public function get_name($type = 'normal')
	{
		switch ($type)
		{
			case ('normal'):
				return 'phpBB Native Fulltext PM Normal';
			break;
		}
	}

	public function get_common_words()
	{
		return $this->common_words;
	}

	/**
	 * Returns the word_length array
	 *
	 * @return array min and max word length for searching
	 */
	public function get_word_length()
	{
		return $this->word_length;
	}

	/**
	* This function fills $this->search_query with the cleaned user search query
	*
	* If $terms is 'any' then the words will be extracted from the search query
	* and combined with | inside brackets. They will afterwards be treated like
	* an standard search query.
	*
	* Then it analyses the query and fills the internal arrays $must_not_contain_ids,
	* $must_contain_ids and $must_exclude_one_ids which are later used by keyword_search()
	*
	* @param	string	$keywords	contains the search query string as entered by the user
	* @param	string	$terms		is either 'all' (use search query as entered, default words to 'must be contained in post')
	* 	or 'any' (find all posts containing at least one of the given words)
	* @return	boolean				false if no valid keywords were found and otherwise true
	*/
	public function split_keywords($keywords, $terms)
	{
		$swl_table = PRIVMSGS_TABLE . '_swl';
		$swm_table = PRIVMSGS_TABLE . '_swm';

		$tokens = '+-|()*';

		$keywords = trim($this->cleanup($keywords, $tokens));

		// allow word|word|word without brackets
		if ((strpos($keywords, ' ') === false) && (strpos($keywords, '|') !== false) && (strpos($keywords, '(') === false))
		{
			$keywords = '(' . $keywords . ')';
		}

		$open_bracket = $space = false;
		for ($i = 0, $n = strlen($keywords); $i < $n; $i++)
		{
			if ($open_bracket !== false)
			{
				switch ($keywords[$i])
				{
					case ')':
						if ($open_bracket + 1 == $i)
						{
							$keywords[$i - 1] = '|';
							$keywords[$i] = '|';
						}
						$open_bracket = false;
					break;
					case '(':
						$keywords[$i] = '|';
					break;
					case '+':
					case '-':
					case ' ':
						$keywords[$i] = '|';
					break;
					case '*':
						if ($i === 0 || ($keywords[$i - 1] !== '*' && strcspn($keywords[$i - 1], $tokens) === 0))
						{
							if ($i === $n - 1 || ($keywords[$i + 1] !== '*' && strcspn($keywords[$i + 1], $tokens) === 0))
							{
								$keywords = substr($keywords, 0, $i) . substr($keywords, $i + 1);
							}
						}
					break;
				}
			}
			else
			{
				switch ($keywords[$i])
				{
					case ')':
						$keywords[$i] = ' ';
					break;
					case '(':
						$open_bracket = $i;
						$space = false;
					break;
					case '|':
						$keywords[$i] = ' ';
					break;
					case '-':
					case '+':
						$space = $keywords[$i];
					break;
					case ' ':
						if ($space !== false)
						{
							$keywords[$i] = $space;
						}
					break;
					default:
						$space = false;
				}
			}
		}

		if ($open_bracket)
		{
			$keywords .= ')';
		}

		$match = array(
			'#  +#',
			'#\|\|+#',
			'#(\+|\-)(?:\+|\-)+#',
			'#\(\|#',
			'#\|\)#',
		);
		$replace = array(
			' ',
			'|',
			'$1',
			'(',
			')',
		);

		$keywords = preg_replace($match, $replace, $keywords);
		$num_keywords = sizeof(explode(' ', $keywords));

		// We limit the number of allowed keywords to minimize load on the database
		if ($this->config['max_num_search_keywords'] && $num_keywords > $this->config['max_num_search_keywords'])
		{
			trigger_error($this->user->lang('MAX_NUM_SEARCH_KEYWORDS_REFINE', (int) $this->config['max_num_search_keywords'], $num_keywords));
		}

		// $keywords input format: each word separated by a space, words in a bracket are not separated

		// the user wants to search for any word, convert the search query
		if ($terms == 'any')
		{
			$words = array();

			preg_match_all('#([^\\s+\\-|()]+)(?:$|[\\s+\\-|()])#u', $keywords, $words);
			if (sizeof($words[1]))
			{
				$keywords = '(' . implode('|', $words[1]) . ')';
			}
		}

		// set the search_query which is shown to the user
		$this->search_query = $keywords;

		$exact_words = array();
		preg_match_all('#([^\\s+\\-|()]+)(?:$|[\\s+\\-|()])#u', $keywords, $exact_words);
		$exact_words = $exact_words[1];

		$common_ids = $words = array();

		if (sizeof($exact_words))
		{
			$sql = 'SELECT word_id, word_text, word_common
				FROM ' . $swl_table . '
				WHERE ' . $this->db->sql_in_set('word_text', $exact_words) . '
				ORDER BY word_count ASC';
			$result = $this->db->sql_query($sql);

			// store an array of words and ids, remove common words
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['word_common'])
				{
					$this->common_words[] = $row['word_text'];
					$common_ids[$row['word_text']] = (int) $row['word_id'];
					continue;
				}

				$words[$row['word_text']] = (int) $row['word_id'];
			}
			$this->db->sql_freeresult($result);
		}

		// Handle +, - without preceeding whitespace character
		$match		= array('#(\S)\+#', '#(\S)-#');
		$replace	= array('$1 +', '$1 +');

		$keywords = preg_replace($match, $replace, $keywords);

		// now analyse the search query, first split it using the spaces
		$query = explode(' ', $keywords);

		$this->must_contain_ids = array();
		$this->must_not_contain_ids = array();
		$this->must_exclude_one_ids = array();

		foreach ($query as $word)
		{
			if (empty($word))
			{
				continue;
			}

			// words which should not be included
			if ($word[0] == '-')
			{
				$word = substr($word, 1);

				// a group of which at least one may not be in the resulting posts
				if ($word[0] == '(')
				{
					$word = array_unique(explode('|', substr($word, 1, -1)));
					$mode = 'must_exclude_one';
				}
				// one word which should not be in the resulting posts
				else
				{
					$mode = 'must_not_contain';
				}
				$ignore_no_id = true;
			}
			// words which have to be included
			else
			{
				// no prefix is the same as a +prefix
				if ($word[0] == '+')
				{
					$word = substr($word, 1);
				}

				// a group of words of which at least one word should be in every resulting post
				if ($word[0] == '(')
				{
					$word = array_unique(explode('|', substr($word, 1, -1)));
				}
				$ignore_no_id = false;
				$mode = 'must_contain';
			}

			if (empty($word))
			{
				continue;
			}

			// if this is an array of words then retrieve an id for each
			if (is_array($word))
			{
				$non_common_words = array();
				$id_words = array();
				foreach ($word as $i => $word_part)
				{
					if (strpos($word_part, '*') !== false)
					{
						$id_words[] = '\'' . $this->db->sql_escape(str_replace('*', '%', $word_part)) . '\'';
						$non_common_words[] = $word_part;
					}
					else if (isset($words[$word_part]))
					{
						$id_words[] = $words[$word_part];
						$non_common_words[] = $word_part;
					}
					else
					{
						$len = utf8_strlen($word_part);
						if ($len < $this->word_length['min'] || $len > $this->word_length['max'])
						{
							$this->common_words[] = $word_part;
						}
					}
				}
				if (sizeof($id_words))
				{
					sort($id_words);
					if (sizeof($id_words) > 1)
					{
						$this->{$mode . '_ids'}[] = $id_words;
					}
					else
					{
						$mode = ($mode == 'must_exclude_one') ? 'must_not_contain' : $mode;
						$this->{$mode . '_ids'}[] = $id_words[0];
					}
				}
				// throw an error if we shall not ignore unexistant words
				else if (!$ignore_no_id && sizeof($non_common_words))
				{
					trigger_error(sprintf($this->user->lang['WORDS_IN_NO_POST'], implode($this->user->lang['COMMA_SEPARATOR'], $non_common_words)));
				}
				unset($non_common_words);
			}
			// else we only need one id
			else if (($wildcard = strpos($word, '*') !== false) || isset($words[$word]))
			{
				if ($wildcard)
				{
					$len = utf8_strlen(str_replace('*', '', $word));
					if ($len >= $this->word_length['min'] && $len <= $this->word_length['max'])
					{
						$this->{$mode . '_ids'}[] = '\'' . $this->db->sql_escape(str_replace('*', '%', $word)) . '\'';
					}
					else
					{
						$this->common_words[] = $word;
					}
				}
				else
				{
					$this->{$mode . '_ids'}[] = $words[$word];
				}
			}
			else
			{
				if (!isset($common_ids[$word]))
				{
					$len = utf8_strlen($word);
					if ($len < $this->word_length['min'] || $len > $this->word_length['max'])
					{
						$this->common_words[] = $word;
					}
				}
			}
		}

		// Return true if all words are not common words
		if (sizeof($exact_words) - sizeof($this->common_words) > 0)
		{
			return true;
		}
		return false;
	}

	/**
	* Performs a search on keywords depending on display specific params. You have to run split_keywords() first
	*
	* @param	string		$type				wchich type of table to be searched defaults to norma
	* @param	string		$fields				contains either titleonly (topic titles should be searched), msgonly (only message bodies should be searched), firstpost (only subject and body of the first post should be searched) or all (all post bodies and subjects should be searched)
	* @param	string		$terms				is either 'all' (use query as entered, words without prefix should default to "have to be in field") or 'any' (ignore search query parts and just return all posts that contain any of the specified words)
	* @param	array		$sort_by_sql		contains SQL code for the ORDER BY part of a query
	* @param	string		$sort_key			is the key of $sort_by_sql for the selected sorting
	* @param	string		$sort_dir			is either a or d representing ASC and DESC
	* @param	string		$sort_days			specifies the maximum amount of days a post may be old
	* @param	array		$author_ary			an array of author ids if the author should be ignored during the search the array is empty
	* @param	string		$author_name		specifies the author match, when ANONYMOUS is also a search-match
	* @param	array		&$id_ary			passed by reference, to be filled with ids for the page specified by $start and $per_page, should be ordered
	* @param	int			$start				indicates the first index of the page
	* @param	int			$per_page			number of ids each page is supposed to contain
	* @return	boolean|int						total number of results
	*/
	public function keyword_search($type, $fields, $terms, $sort_by_sql, $sort_key, $sort_dir, $sort_days, $ex_fid_ary, $post_visibility, $topic_id, $author_ary, $author_name, &$id_ary, &$start, $per_page)
	{
		// No keywords? No posts.
		if (empty($this->search_query))
		{
			return false;
		}

		// we can't search for negatives only
		if (empty($this->must_contain_ids))
		{
			return false;
		}
		$swl_table = PRIVMSGS_TABLE . '_swl';
		$swm_table = PRIVMSGS_TABLE . '_swm';
		$message_table = PRIVMSGS_TABLE;
		$message_to_table = PRIVMSGS_TO_TABLE;

		$must_contain_ids = $this->must_contain_ids;
		$must_not_contain_ids = $this->must_not_contain_ids;
		$must_exclude_one_ids = $this->must_exclude_one_ids;

		sort($must_contain_ids);
		sort($must_not_contain_ids);
		sort($must_exclude_one_ids);

		// generate a search_key from all the options to identify the results
		$search_key_array = array(
			serialize($must_contain_ids),
			serialize($must_not_contain_ids),
			serialize($must_exclude_one_ids),
			$type,
			$fields,
			$terms,
			$sort_days,
			$sort_key,
			$topic_id,
			implode(',', $ex_fid_ary),
			$post_visibility,
			implode(',', $author_ary),
			$author_name,
		);

		$search_key = md5(implode('#', $search_key_array));

		// try reading the results from cache
		$total_results = 0;
		if ($this->obtain_ids($search_key, $total_results, $id_ary, $start, $per_page, $sort_dir) == SEARCH_RESULT_IN_CACHE)
		{
			return $total_results;
		}

		$id_ary = array();

		$sql_where = array();
		$m_num = 0;
		$w_num = 0;

		$sql_array = array(
			'SELECT'	=> 'msg.msg_id',
			'FROM'		=> array(
				$swm_table	=> array(),
				$swl_table	=> array(),
			),
			'LEFT_JOIN' => array(array(
									 'FROM'	=> array(
										 $message_to_table	=> 'msg',
									 ),
									 'WHERE'	=> '',
									 'ON'	=> 'm0.post_id = msg.msg_id',
								 )),
		);

		$title_match = '';
		$left_join_topics = false;
		$group_by = true;
		// Build some display specific sql strings
		switch ($fields)
		{
			case 'titleonly':
				$title_match = 'title_match = 1';
				$group_by = false;
			break;

			case 'msgonly':
				$title_match = 'title_match = 0';
				$group_by = false;
			break;
		}

		/**
		 * @todo Add a query optimizer (handle stuff like "+(4|3) +4")
		 */

		foreach ($this->must_contain_ids as $subquery)
		{
			if (is_array($subquery))
			{
				$group_by = true;

				$word_id_sql = array();
				$word_ids = array();
				foreach ($subquery as $id)
				{
					if (is_string($id))
					{
						$sql_array['LEFT_JOIN'][] = array(
							'FROM'	=> array($swl_table => 'w' . $w_num),
							'ON'	=> "w$w_num.word_text LIKE $id"
						);
						$word_ids[] = "w$w_num.word_id";

						$w_num++;
					}
					else
					{
						$word_ids[] = $id;
					}
				}

				$sql_where[] = $this->db->sql_in_set("m$m_num.word_id", $word_ids);

				unset($word_id_sql);
				unset($word_ids);
			}
			else if (is_string($subquery))
			{
				$sql_array['FROM'][$swl_table][] = 'w' . $w_num;

				$sql_where[] = "w$w_num.word_text LIKE $subquery";
				$sql_where[] = "m$m_num.word_id = w$w_num.word_id";

				$group_by = true;
				$w_num++;
			}
			else
			{
				$sql_where[] = "m$m_num.word_id = $subquery";
			}

			$sql_array['FROM'][$swm_table][] = 'm' . $m_num;

			if ($title_match)
			{
				$sql_where[] = "m$m_num.$title_match";
			}

			if ($m_num != 0)
			{
				$sql_where[] = "m$m_num.post_id = m0.post_id";
			}
			$m_num++;
		}

		foreach ($this->must_not_contain_ids as $key => $subquery)
		{
			if (is_string($subquery))
			{
				$sql_array['LEFT_JOIN'][] = array(
					'FROM'	=> array($swl_table => 'w' . $w_num),
					'ON'	=> "w$w_num.word_text LIKE $subquery"
				);

				$this->must_not_contain_ids[$key] = "w$w_num.word_id";

				$group_by = true;
				$w_num++;
			}
		}

		if (sizeof($this->must_not_contain_ids))
		{
			$sql_array['LEFT_JOIN'][] = array(
				'FROM'	=> array($swm_table => 'm' . $m_num),
				'ON'	=> $this->db->sql_in_set("m$m_num.word_id", $this->must_not_contain_ids) . (($title_match) ? " AND m$m_num.$title_match" : '') . " AND m$m_num.post_id = m0.post_id"
			);

			$sql_where[] = "m$m_num.word_id IS NULL";
			$m_num++;
		}

		foreach ($this->must_exclude_one_ids as $ids)
		{
			$is_null_joins = array();
			foreach ($ids as $id)
			{
				if (is_string($id))
				{
					$sql_array['LEFT_JOIN'][] = array(
						'FROM'	=> array($swl_table => 'w' . $w_num),
						'ON'	=> "w$w_num.word_text LIKE $id"
					);
					$id = "w$w_num.word_id";

					$group_by = true;
					$w_num++;
				}

				$sql_array['LEFT_JOIN'][] = array(
					'FROM'	=> array($swm_table => 'm' . $m_num),
					'ON'	=> "m$m_num.word_id = $id AND m$m_num.post_id = m0.post_id" . (($title_match) ? " AND m$m_num.$title_match" : '')
				);
				$is_null_joins[] = "m$m_num.word_id IS NULL";

				$m_num++;
			}
			$sql_where[] = '(' . implode(' OR ', $is_null_joins) . ')';
		}
		//$sql_where[] = $post_visibility;

		$search_query = $this->search_query;
		$must_exclude_one_ids = $this->must_exclude_one_ids;
		$must_not_contain_ids = $this->must_not_contain_ids;
		$must_contain_ids = $this->must_contain_ids;

		if (sizeof($author_ary))
		{
			$folders = array(-2, -1);
			//$sql_author = '((' . $this->db->sql_in_set('msg.author_id', $author_ary) . ' or ' . $this->db->sql_in_set('msg.user_id', $author_ary) . ') and (msg.user_id <> msg.author_id or (msg.user_id = msg.author_id and ' . $this->db->sql_in_set('msg.folder_id', $folders, true) . ')))';
			$sql_author = '(' . $this->db->sql_in_set('msg.author_id', $author_ary) . ' or ' . $this->db->sql_in_set('msg.user_id', $author_ary) . ')';
			$sql_where[] = $sql_author;

		}

		if ($sort_days)
		{
			$sql_where[] = 'msg.message_time >= ' . (time() - ($sort_days * 86400));
		}

		$sql_array['WHERE'] = implode(' AND ', $sql_where);

		$is_mysql = false;
		// if the total result count is not cached yet, retrieve it from the db
		if (!$total_results)
		{
			$sql = '';
			$sql_array_count = $sql_array;
			switch ($this->db->get_sql_layer())
			{
				case 'mysql4':
				case 'mysqli':

					// 3.x does not support SQL_CALC_FOUND_ROWS
					// $sql_array['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $sql_array['SELECT'];
					$is_mysql = true;

				break;

				case 'sqlite3':
					$sql_array_count['SELECT'] = 'DISTINCT msg.msg_id';
					$sql = 'SELECT COUNT(msg_id) as total_results
							FROM (' . $this->db->sql_build_query('SELECT_DISTINCT', $sql_array_count) . ')';

				// no break

				default:
					$sql_array_count['SELECT'] = 'COUNT(DISTINCT msg.msg_id) AS total_results';
					$sql = (!$sql) ? $this->db->sql_build_query('SELECT', $sql_array_count) : $sql;

					$result = $this->db->sql_query($sql);
					$total_results = (int) $this->db->sql_fetchfield('total_results');
					$this->db->sql_freeresult($result);

					if (!$total_results)
					{
						return false;
					}
				break;
			}

			unset($sql_array_count, $sql);
		}

		// Build sql strings for sorting
		$sql_sort = $sort_by_sql[$sort_key] . (($sort_dir == 'a') ? ' ASC' : ' DESC');

		// if using mysql and the total result count is not calculated yet, get it from the db
		if (!$total_results && $is_mysql)
		{
			// Also count rows for the query as if there was not LIMIT. Add SQL_CALC_FOUND_ROWS to SQL
			$sql_array['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $sql_array['SELECT'];
		}

		$sql_array['WHERE'] = implode(' AND ', $sql_where);
		//$sql_array['GROUP_BY'] = ($group_by) ? (($type == 'posts') ? 'p.post_id' : 'p.topic_id') . ', ' . $sort_by_sql[$sort_key] : '';
		$sql_array['ORDER_BY'] = 'msg.msg_id DESC';

		unset($sql_where, $sql_sort, $group_by);

		$sql = $this->db->sql_build_query('SELECT_DISTINCT', $sql_array);

		$result = $this->db->sql_query_limit($sql, $this->config['search_block_size'], $start);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$id_ary[] = (int) $row['msg_id'];
		}
		$this->db->sql_freeresult($result);

		if (!$total_results && $is_mysql)
		{
			// Get the number of results as calculated by MySQL
			$sql_count = 'SELECT FOUND_ROWS() as total_results';
			$result = $this->db->sql_query($sql_count);
			$total_results = (int) $this->db->sql_fetchfield('total_results');
			$this->db->sql_freeresult($result);

			if (!$total_results)
			{
				return false;
			}
		}

		if ($start >= $total_results)
		{
			$start = floor(($total_results - 1) / $per_page) * $per_page;

			$result = $this->db->sql_query_limit($sql, $this->config['search_block_size'], $start);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$id_ary[] = (int) $row['msg_id'];
			}
			$this->db->sql_freeresult($result);

		}

		// store the ids, from start on then delete anything that isn't on the current page because we only need ids for one page
		$this->save_ids($search_key, $this->search_query, $author_ary, $total_results, $id_ary, $start, $sort_dir);
		$id_ary = array_slice($id_ary, 0, (int) $per_page);

		return $total_results;
	}

	/**
	* Updates wordlist and wordmatch tables when a message is posted or changed
	*
	* @param	string	$mode		Contains the post mode: edit, post, reply, quote
	* @param	int		$post_id	The id of the post which is modified/created
	* @param	string	&$message	New or updated post content
	* @param	string	&$subject	New or updated post subject
	* @param	int		$poster_id	Post author's user id
	* @param	int		$forum_id	The id of the forum in which the post is located
	*/
	public function index($mode, $post_id, &$message, &$subject, $poster_id, $forum_id = '')
	{
		$wordlist = PRIVMSGS_TABLE . '_swl';
		$wordmatch = PRIVMSGS_TABLE . '_swm';

		if (!$this->config['fulltext_native_load_upd'])
		{
			/**
			 * The search indexer is disabled, return
			 */
			return;
		}

		// Split old and new post/subject to obtain array of 'words'
		$split_text = $this->split_message($message);
		$split_title = $this->split_message($subject);

		$cur_words = array('post' => array(), 'title' => array());

		$words = array();
		if ($mode == 'edit')
		{
			$words['add']['post'] = array();
			$words['add']['title'] = array();
			$words['del']['post'] = array();
			$words['del']['title'] = array();

			$sql = 'SELECT w.word_id, w.word_text, m.title_match
				FROM ' . $wordlist . ' w, ' . $wordmatch . " m
				WHERE m.post_id = $post_id
					AND w.word_id = m.word_id";
			$result = $this->db->sql_query($sql);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$which = ($row['title_match']) ? 'title' : 'post';
				$cur_words[$which][$row['word_text']] = $row['word_id'];
			}
			$this->db->sql_freeresult($result);

			$words['add']['post'] = array_diff($split_text, array_keys($cur_words['post']));
			$words['add']['title'] = array_diff($split_title, array_keys($cur_words['title']));
			$words['del']['post'] = array_diff(array_keys($cur_words['post']), $split_text);
			$words['del']['title'] = array_diff(array_keys($cur_words['title']), $split_title);
		}
		else
		{
			$words['add']['post'] = $split_text;
			$words['add']['title'] = $split_title;
			$words['del']['post'] = array();
			$words['del']['title'] = array();
		}
		unset($split_text);
		unset($split_title);

		// Get unique words from the above arrays
		$unique_add_words = array_unique(array_merge($words['add']['post'], $words['add']['title']));

		// We now have unique arrays of all words to be added and removed and
		// individual arrays of added and removed words for text and title. What
		// we need to do now is add the new words (if they don't already exist)
		// and then add (or remove) matches between the words and this post
		if (sizeof($unique_add_words))
		{
			$sql = 'SELECT word_id, word_text
				FROM ' . $wordlist . '
				WHERE ' . $this->db->sql_in_set('word_text', $unique_add_words);
			$result = $this->db->sql_query($sql);

			$word_ids = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$word_ids[$row['word_text']] = $row['word_id'];
			}
			$this->db->sql_freeresult($result);
			$new_words = array_diff($unique_add_words, array_keys($word_ids));

			$this->db->sql_transaction('begin');
			if (sizeof($new_words))
			{
				$sql_ary = array();

				foreach ($new_words as $word)
				{
					$sql_ary[] = array('word_text' => (string) $word, 'word_count' => 0);
				}
				$this->db->sql_return_on_error(true);
				$this->db->sql_multi_insert($wordlist, $sql_ary);
				$this->db->sql_return_on_error(false);
			}
			unset($new_words, $sql_ary);
		}
		else
		{
			$this->db->sql_transaction('begin');
		}

		// now update the search match table, remove links to removed words and add links to new words
		foreach ($words['del'] as $word_in => $word_ary)
		{
			$title_match = ($word_in == 'title') ? 1 : 0;

			if (sizeof($word_ary))
			{
				$sql_in = array();
				foreach ($word_ary as $word)
				{
					$sql_in[] = $cur_words[$word_in][$word];
				}

				$sql = 'DELETE FROM ' . $wordmatch . '
					WHERE ' . $this->db->sql_in_set('word_id', $sql_in) . '
						AND post_id = ' . intval($post_id) . "
						AND title_match = $title_match";
				$this->db->sql_query($sql);

				$sql = 'UPDATE ' . $wordlist . '
					SET word_count = word_count - 1
					WHERE ' . $this->db->sql_in_set('word_id', $sql_in) . '
						AND word_count > 0';
				$this->db->sql_query($sql);

				unset($sql_in);
			}
		}

		$this->db->sql_return_on_error(true);
		foreach ($words['add'] as $word_in => $word_ary)
		{
			$title_match = ($word_in == 'title') ? 1 : 0;

			if (sizeof($word_ary))
			{
				$sql = 'INSERT INTO ' . $wordmatch . ' (post_id, word_id, title_match)
					SELECT ' . (int) $post_id . ', word_id, ' . (int) $title_match . '
					FROM ' . $wordlist . '
					WHERE ' . $this->db->sql_in_set('word_text', $word_ary);
				$this->db->sql_query($sql);

				$sql = 'UPDATE ' . $wordlist . '
					SET word_count = word_count + 1
					WHERE ' . $this->db->sql_in_set('word_text', $word_ary);
				$this->db->sql_query($sql);
			}
		}
		$this->db->sql_return_on_error(false);

		$this->db->sql_transaction('commit');

		// destroy cached search results containing any of the words removed or added
		$this->destroy_cache(array_unique(array_merge($words['add']['post'], $words['add']['title'], $words['del']['post'], $words['del']['title'])), array($poster_id));

		unset($unique_add_words);
		unset($words);
		unset($cur_words);
	}
	/**
	 * Removes entries from the wordmatch table for the specified post_ids
	 */
	public function index_remove($post_ids, $author_ids = null, $forum_ids = null)
	{
		if (sizeof($post_ids))
		{
			$sql = 'SELECT w.word_id, w.word_text, m.title_match
				FROM ' . PRIVMSGS_TABLE . '_swm' . ' m, ' . PRIVMSGS_TABLE . '_swl' . ' w
				WHERE ' . $this->db->sql_in_set('m.post_id', $post_ids) . '
					AND w.word_id = m.word_id';
			$result = $this->db->sql_query($sql);

			$message_word_ids = $title_word_ids = $word_texts = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['title_match'])
				{
					$title_word_ids[] = $row['word_id'];
				}
				else
				{
					$message_word_ids[] = $row['word_id'];
				}
				$word_texts[] = $row['word_text'];
			}
			$this->db->sql_freeresult($result);

			if (sizeof($title_word_ids))
			{
				$sql = 'UPDATE ' .  PRIVMSGS_TABLE . '_swl' . '
					SET word_count = word_count - 1
					WHERE ' . $this->db->sql_in_set('word_id', $title_word_ids) . '
						AND word_count > 0';
				$this->db->sql_query($sql);
			}

			if (sizeof($message_word_ids))
			{
				$sql = 'UPDATE ' . PRIVMSGS_TABLE . '_swl' . '
					SET word_count = word_count - 1
					WHERE ' . $this->db->sql_in_set('word_id', $message_word_ids) . '
						AND word_count > 0';
				$this->db->sql_query($sql);
			}

			unset($title_word_ids);
			unset($message_word_ids);

			$sql = 'DELETE FROM ' . PRIVMSGS_TABLE . '_swm' . '
				WHERE ' . $this->db->sql_in_set('post_id', $post_ids);
			$this->db->sql_query($sql);
		}

		$this->destroy_cache(array_unique($word_texts));
	}

	/**
	* Tidy up indexes: Tag 'common words' and remove
	* words no longer referenced in the match table
	*/
	public function tidy()
	{
		$swl_table = PRIVMSGS_TABLE . '_swl';
		$swm_table = PRIVMSGS_TABLE . '_swm';
		// Is the fulltext indexer disabled? If yes then we need not
		// carry on ... it's okay ... I know when I'm not wanted boo hoo
		if (!$this->config['fulltext_native_load_upd'])
		{
			$this->config->set('search_last_gc', time(), false);
			return;
		}

		$destroy_cache_words = array();

		// Remove common words
		/*
		if ($this->config['num_posts'] >= 100 && $this->config['fulltext_native_common_thres'])
		{
			$common_threshold = ((double) $this->config['fulltext_native_common_thres']) / 100.0;
			// First, get the IDs of common words
			$sql = 'SELECT word_id, word_text
				FROM ' . SEARCH_WORDLIST_TABLE . '
				WHERE word_count > ' . floor($this->config['num_posts'] * $common_threshold) . '
					OR word_common = 1';
			$result = $this->db->sql_query($sql);

			$sql_in = array();
			while ($row = $this->db->sql_fetchrow($result))
			{
				$sql_in[] = $row['word_id'];
				$destroy_cache_words[] = $row['word_text'];
			}
			$this->db->sql_freeresult($result);

			if (sizeof($sql_in))
			{
				// Flag the words
				$sql = 'UPDATE ' . SEARCH_WORDLIST_TABLE . '
					SET word_common = 1
					WHERE ' . $this->db->sql_in_set('word_id', $sql_in);
				$this->db->sql_query($sql);

				// by setting search_last_gc to the new time here we make sure that if a user reloads because the
				// following query takes too long, he won't run into it again
				$this->config->set('search_last_gc', time(), false);

				// Delete the matches
				$sql = 'DELETE FROM ' . SEARCH_WORDMATCH_TABLE . '
					WHERE ' . $this->db->sql_in_set('word_id', $sql_in);
				$this->db->sql_query($sql);
			}
			unset($sql_in);
		}
		*/
		if (sizeof($destroy_cache_words))
		{
			// destroy cached search results containing any of the words that are now common or were removed
			$this->destroy_cache(array_unique($destroy_cache_words));
		}

		$this->config->set('search_last_gc', time(), false);
	}

	/**
	* Deletes all words from the index
	*/
	public function delete_index($acp_module, $u_action, $type = 'normal')
	{
		$swl_table = PRIVMSGS_TABLE . '_swl';
		$swm_table = PRIVMSGS_TABLE . '_swm';

		switch ($this->db->get_sql_layer())
		{
			case 'sqlite':
			case 'sqlite3':
				$this->db->sql_query('DELETE FROM ' . $swl_table);
				$this->db->sql_query('DELETE FROM ' . $swm_table);
				//$this->db->sql_query('DELETE FROM ' . SEARCH_RESULTS_TABLE . '');
			break;

			default:
				$this->db->sql_query('TRUNCATE TABLE ' . $swl_table);
				$this->db->sql_query('TRUNCATE TABLE ' . $swm_table);
				$this->db->sql_query('TRUNCATE TABLE ' . SEARCH_RESULTS_TABLE);
			break;
		}
	}

	/**
	* Returns true if both FULLTEXT indexes exist
	*/
	public function index_created($type = 'normal')
	{
		if (!sizeof($this->stats))
		{
			$this->get_stats();
		}
		switch ($type)
		{
			case 'normal':
				return ($this->stats['total_words'] && $this->stats['total_matches']) ? true : false;
			break;
		}
	}

	/**
	* Returns an associative array containing information about the indexes
	*/
	public function index_stats($type = 'normal')
	{
		if (!sizeof($this->stats))
		{
			$this->get_stats();
		}
		switch ($type)
		{
			case 'normal':
				return array(
					$this->user->lang['TOTAL_WORDS']		=> $this->stats['total_words'],
					$this->user->lang['TOTAL_MATCHES']	=> $this->stats['total_matches']);
			break;
		}
	}

	protected function get_stats()
	{
		$this->stats['total_words']		= $this->db->get_estimated_row_count(PRIVMSGS_TABLE . '_swl');
		$this->stats['total_matches']	= $this->db->get_estimated_row_count(PRIVMSGS_TABLE . '_swm');
	}

	/**
	 * Get corespondence with user ...
	 *
	 * This is a part of the july sprint for F-bg.org
	 *
	 * @param $target_id	Should be the target user's ID
	 * @param $start		If we have pagination - start page
	 * @param $per_page
	 * @return bool
	 */
	public function user_search($target_id, &$id_ary, &$start, $per_page)
	{
		$messages_table = PRIVMSGS_TABLE;
		$messages_to_table = PRIVMSGS_TO_TABLE;

		//Sanity check -> are we getting user id or are we being scamed.

		if (!is_numeric($target_id))
		{
			return false;
		}
		// Let's get messages we have between users (and don't filter them, becouse we don't know who deleted what.
		$sql_array = array(
			'SELECT'	=> 'msg.msg_id',
			'FROM'		=> array(
				$messages_table => 'msg',
				$messages_to_table => 'mt',
			),

			'WHERE'	=> 'msg.msg_id = mt.msg_id AND (((msg.author_id = ' . $this->user->data['user_id'] . ' AND msg.to_address LIKE \'u_' . $target_id .'\') OR (msg.author_id = ' . $target_id . ' AND msg.to_address LIKE \'u_' . $this->user->data['user_id'] .'\')) AND mt.user_id = ' . $this->user->data['user_id'] . ')',

		);

		// Let's get counts and such
		$total_results = 0;
		$is_mysql = false;
		// if the total result count is not cached yet, retrieve it from the db
		if (!$total_results)
		{
			$sql = '';
			$sql_array_count = $sql_array;
			switch ($this->db->get_sql_layer())
			{
				case 'mysql4':
				case 'mysqli':

					// 3.x does not support SQL_CALC_FOUND_ROWS
					// $sql_array['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $sql_array['SELECT'];
					$is_mysql = true;

				break;

				case 'sqlite3':
					$sql_array_count['SELECT'] = 'DISTINCT msg.msg_id';
					$sql = 'SELECT COUNT(msg_id) as total_results
							FROM (' . $this->db->sql_build_query('SELECT_DISTINCT', $sql_array_count) . ')';

				// no break

				default:
					$sql_array_count['SELECT'] = 'COUNT(DISTINCT msg.msg_id) AS total_results';
					$sql = (!$sql) ? $this->db->sql_build_query('SELECT', $sql_array_count) : $sql;

					$result = $this->db->sql_query($sql);
					$total_results = (int) $this->db->sql_fetchfield('total_results');
					$this->db->sql_freeresult($result);

					if (!$total_results)
					{
						return false;
					}
				break;
			}

			unset($sql_array_count, $sql);
		}
		// if using mysql and the total result count is not calculated yet, get it from the db
		if (!$total_results && $is_mysql)
		{
			// Also count rows for the query as if there was not LIMIT. Add SQL_CALC_FOUND_ROWS to SQL
			$sql_array['SELECT'] = 'SQL_CALC_FOUND_ROWS ' . $sql_array['SELECT'];
		}

		$sql_array['ORDER_BY'] = 'msg.msg_id DESC';

		$sql = $this->db->sql_build_query('SELECT_DISTINCT', $sql_array);

		$result = $this->db->sql_query_limit($sql, $this->config['search_block_size'], $start);

		while ($row = $this->db->sql_fetchrow($result))
		{
			$id_ary[] = (int) $row['msg_id'];
		}
		$this->db->sql_freeresult($result);

		if (!$total_results && $is_mysql)
		{
			// Get the number of results as calculated by MySQL
			$sql_count = 'SELECT FOUND_ROWS() as total_results';
			$result = $this->db->sql_query($sql_count);
			$total_results = (int) $this->db->sql_fetchfield('total_results');
			$this->db->sql_freeresult($result);

			if (!$total_results)
			{
				return false;
			}
		}

		if ($start >= $total_results)
		{
			$start = floor(($total_results - 1) / $per_page) * $per_page;

			$result = $this->db->sql_query_limit($sql, $this->config['search_block_size'], $start);

			while ($row = $this->db->sql_fetchrow($result))
			{
				$id_ary[] = (int) $row['msg_id'];
			}
			$this->db->sql_freeresult($result);

		}

		// store the ids, from start on then delete anything that isn't on the current page because we only need ids for one page
		$search_key = array(
			$this->user->data['user_id'],
			$target_id
		);
		$search_key = implode(';', $search_key);
		$author_ary = array();
		$sort_dir = 'a';
		$this->save_ids(md5($search_key), $this->search_query, $author_ary, $total_results, $id_ary, $start, $sort_dir);
		$id_ary = array_slice($id_ary, 0, (int) $per_page);

		return $total_results;

	}
}
