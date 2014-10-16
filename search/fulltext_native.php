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
* @ignore
*/
if (!defined('SEARCH_RESULT_NOT_IN_CACHE'))
{
	define('SEARCH_RESULT_NOT_IN_CACHE', 0);
}

if (!defined('SEARCH_RESULT_IN_CACHE'))
{
	define('SEARCH_RESULT_IN_CACHE', 1);
}

if (!defined('SEARCH_RESULT_INCOMPLETE'))
{
	define('SEARCH_RESULT_INCOMPLETE', 2);
}

/**
* phpBB's own db driven fulltext search, version 2
*/
class fulltext_native
{
	/**
	 * Associative array holding index stats
	 * @var array
	 */
	protected $stats = array();

	/**
	 * Associative array stores the min and max word length to be searched
	 * @var array
	 */
	protected $word_length = array();

	/**
	 * Contains tidied search query.
	 * Operators are prefixed in search query and common words excluded
	 * @var string
	 */
	protected $search_query;

	/**
	 * Contains common words.
	 * Common words are words with length less/more than min/max length
	 * @var array
	 */
	protected $common_words = array();

	/**
	 * Post ids of posts containing words that are to be included
	 * @var array
	 */
	protected $must_contain_ids = array();

	/**
	 * Post ids of posts containing words that should not be included
	 * @var array
	 */
	protected $must_not_contain_ids = array();

	/**
	 * Post ids of posts containing atleast one word that needs to be excluded
	 * @var array
	 */
	protected $must_exclude_one_ids = array();

	/**
	 * Relative path to board root
	 * @var string
	 */
	protected $phpbb_root_path;

	/**
	 * PHP Extension
	 * @var string
	 */
	protected $php_ext;

	/**
	 * Config object
	 * @var \phpbb\config\config
	 */
	protected $config;

	/**
	 * Database connection
	 * @var \phpbb\db\driver\driver_interface
	 */
	protected $db;

	/**
	 * User object
	 * @var \phpbb\user
	 */
	protected $user;

	/**
	* Initialises the fulltext_native search backend with min/max word length and makes sure the UTF-8 normalizer is loaded
	*
	* @param	boolean|string	&$error	is passed by reference and should either be set to false on success or an error message on failure
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\user $user, $table_prefix, $phpbb_root_path, $phpEx)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->table_prefix = $table_prefix;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;
		$this->word_length = array('min' => $this->config['fulltext_native_min_chars'], 'max' => $this->config['fulltext_native_max_chars']);

		/**
		* Load the UTF tools
		*/
		if (!class_exists('utf_normalizer'))
		{
			include($this->phpbb_root_path . 'includes/utf/utf_normalizer.' . $this->php_ext);
		}
		if (!function_exists('utf8_decode_ncr'))
		{
			include($this->phpbb_root_path . 'includes/utf/utf_tools.' . $this->php_ext);
		}

		$error = false;
	}

	/**
	* Returns the name of this search backend to be displayed to administrators
	*
	* @return string Name
	*/
	public function get_name()
	{
		return 'phpBB PM Native Fulltext';
	}

	/**
	 * Returns the search_query
	 *
	 * @return string search query
	 */
	public function get_search_query()
	{
		return $this->search_query;
	}

	/**
	 * Returns the common_words array
	 *
	 * @return array common words that are ignored by search backend
	 */
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
		preg_match_all('#([^\\s+\\-|*()]+)(?:$|[\\s+\\-|()])#u', $keywords, $exact_words);
		$exact_words = $exact_words[1];

		$common_ids = $words = array();

		if (sizeof($exact_words))
		{
			$sql = 'SELECT word_id, word_text, word_common
				FROM ' . PRIVMSGS_TABLE . '_swl' . '
				WHERE ' . $this->db->sql_in_set('word_text', $exact_words) . '
				ORDER BY word_count ASC';
			$result = $this->db->sql_query($sql);

			// store an array of words and ids, remove common words
			while ($row = $this->db->sql_fetchrow($result))
			{
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

		$mode = '';
		$ignore_no_id = true;

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
					trigger_error(sprintf($user->lang['WORDS_IN_NO_POST'], implode($user->lang['COMMA_SEPARATOR'], $non_common_words)));
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
	* @param	string		$fields				contains either titleonly (topic titles should be searched), msgonly (only message bodies should be searched), firstpost (only subject and body of the first post should be searched) or all (all post bodies and subjects should be searched)
	* @param	string		$terms				is either 'all' (use query as entered, words without prefix should default to "have to be in field") or 'any' (ignore search query parts and just return all posts that contain any of the specified words)
	* @param	string		$sort_dir			is either a or d representing ASC and DESC
	* @param	string		$sort_days			specifies the maximum amount of days a post may be old
	* @param	array		$author_ary			an array of author ids if the author should be ignored during the search the array is empty
	* @param	array		&$id_ary			passed by reference, to be filled with ids for the page specified by $start and $per_page, should be ordered
	* @param	int			$start				indicates the first index of the page
	* @param	int			$per_page			number of ids each page is supposed to contain
	* @return	boolean|int						total number of results
	*/
	public function keyword_search($fields, $terms, $sort_dir, $sort_days, $author_ary, &$id_ary, &$start, $per_page)
	{
		// No keywords? No posts.
		if (empty($this->search_query))
		{
			return false;
		}

		// define tables we will change
		$swl_table = PRIVMSGS_TABLE . '_swl';
		$swm_table = PRIVMSGS_TABLE . '_swm';
		$message_table = PRIVMSGS_TABLE;
		$message_to_table = PRIVMSGS_TO_TABLE;
		// we can't search for negatives only
		if (empty($this->must_contain_ids))
		{
			return false;
		}

		$must_contain_ids = $this->must_contain_ids;
		$must_not_contain_ids = $this->must_not_contain_ids;
		$must_exclude_one_ids = $this->must_exclude_one_ids;

		sort($must_contain_ids);
		sort($must_not_contain_ids);
		sort($must_exclude_one_ids);

		// generate a search_key from all the options to identify the results
		$search_key = md5(implode('#', array(
			serialize($must_contain_ids),
			serialize($must_not_contain_ids),
			serialize($must_exclude_one_ids),
			$fields,
			$terms,
			$sort_days,
			implode(',', $author_ary),
		)));

		// try reading the results from cache
		$total_results = 0;
		if ($this->obtain_ids($search_key, $total_results, $id_ary, $start, $per_page, $sort_dir) == SEARCH_RESULT_IN_CACHE)
		{
			return $total_results;
		}

		$id_ary = array();

		$sql_where = array();
		$group_by = false;
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

				case 'sqlite':
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

		// if we use mysql and the total result count is not cached yet, retrieve it from the db
		if (!$total_results && $is_mysql)
		{
			// Count rows for the executed queries. Replace $select within $sql with SQL_CALC_FOUND_ROWS, and run it
			$sql_array_copy = $sql_array;
			$sql_array_copy['SELECT'] = 'SQL_CALC_FOUND_ROWS msg.msg_id ';

			$sql_calc = $this->db->sql_build_query('SELECT_DISTINCT', $sql_array_copy);
			unset($sql_array_copy);
			$this->db->sql_query($sql_calc);
			$this->db->sql_freeresult($result);

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
	* Split a text into words of a given length
	*
	* The text is converted to UTF-8, cleaned up, and split. Then, words that
	* conform to the defined length range are returned in an array.
	*
	* NOTE: duplicates are NOT removed from the return array
	*
	* @param	string	$text	Text to split, encoded in UTF-8
	* @return	array			Array of UTF-8 words
	*/
	public function split_message($text)
	{
		$match = $words = array();

		/**
		* Taken from the original code
		*/
		// Do not index code
		$match[] = '#\[code(?:=.*?)?(\:?[0-9a-z]{5,})\].*?\[\/code(\:?[0-9a-z]{5,})\]#is';
		// BBcode
		$match[] = '#\[\/?[a-z0-9\*\+\-]+(?:=.*?)?(?::[a-z])?(\:?[0-9a-z]{5,})\]#';

		$min = $this->word_length['min'];
		$max = $this->word_length['max'];

		$isset_min = $min - 1;

		/**
		* Clean up the string, remove HTML tags, remove BBCodes
		*/
		$word = strtok($this->cleanup(preg_replace($match, ' ', strip_tags($text)), -1), ' ');

		while (strlen($word))
		{
			if (strlen($word) > 255 || strlen($word) <= $isset_min)
			{
				/**
				* Words longer than 255 bytes are ignored. This will have to be
				* changed whenever we change the length of search_wordlist.word_text
				*
				* Words shorter than $isset_min bytes are ignored, too
				*/
				$word = strtok(' ');
				continue;
			}

			$len = utf8_strlen($word);

			/**
			* Test whether the word is too short to be indexed.
			*
			* Note that this limit does NOT apply to CJK and Hangul
			*/
			if ($len < $min)
			{
				/**
				* Note: this could be optimized. If the codepoint is lower than Hangul's range
				* we know that it will also be lower than CJK ranges
				*/
				if ((strncmp($word, UTF8_HANGUL_FIRST, 3) < 0 || strncmp($word, UTF8_HANGUL_LAST, 3) > 0)
					&& (strncmp($word, UTF8_CJK_FIRST, 3) < 0 || strncmp($word, UTF8_CJK_LAST, 3) > 0)
					&& (strncmp($word, UTF8_CJK_B_FIRST, 4) < 0 || strncmp($word, UTF8_CJK_B_LAST, 4) > 0))
				{
					$word = strtok(' ');
					continue;
				}
			}

			$words[] = $word;
			$word = strtok(' ');
		}

		return $words;
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
	public function index($mode, $post_id, &$message, &$subject, $poster_id)
	{
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
				FROM ' . PRIVMSGS_TABLE . '_swl' . ' w, ' . PRIVMSGS_TABLE . '_swm' . " m
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
				FROM ' . PRIVMSGS_TABLE . '_swl' . '
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
				$this->db->sql_multi_insert(PRIVMSGS_TABLE . '_swl', $sql_ary);
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

				$sql = 'DELETE FROM ' . PRIVMSGS_TABLE . '_swm' . '
					WHERE ' . $this->db->sql_in_set('word_id', $sql_in) . '
						AND post_id = ' . intval($post_id) . "
						AND title_match = $title_match";
				$this->db->sql_query($sql);

				$sql = 'UPDATE ' . PRIVMSGS_TABLE . '_swl' . '
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
				$sql = 'INSERT INTO ' . PRIVMSGS_TABLE . '_swm' . ' (post_id, word_id, title_match)
					SELECT ' . (int) $post_id . ', word_id, ' . (int) $title_match . '
					FROM ' . PRIVMSGS_TABLE . '_swl' . '
					WHERE ' . $this->db->sql_in_set('word_text', $word_ary);
				$this->db->sql_query($sql);

				$sql = 'UPDATE ' . PRIVMSGS_TABLE . '_swl' . '
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
	public function index_remove($post_ids)
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
				$sql = 'UPDATE ' . PRIVMSGS_TABLE . '_swl' . '
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
		// Is the fulltext indexer disabled? If yes then we need not
		// carry on ... it's okay ... I know when I'm not wanted boo hoo
		if (!$this->config['fulltext_native_load_upd'])
		{
			set_config('search_last_gc', time(), true);
			return;
		}

		$destroy_cache_words = array();

		// Remove common words
		if ($this->config['num_posts'] >= 100 && $this->config['fulltext_native_common_thres'])
		{
			$common_threshold = ((double) $this->config['fulltext_native_common_thres']) / 100.0;
			// First, get the IDs of common words
			$sql = 'SELECT word_id, word_text
				FROM ' . PRIVMSGS_TABLE . '_swl' . '
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
				$sql = 'UPDATE ' . PRIVMSGS_TABLE . '_swl' . '
					SET word_common = 1
					WHERE ' . $this->db->sql_in_set('word_id', $sql_in);
				$this->db->sql_query($sql);

				// by setting search_last_gc to the new time here we make sure that if a user reloads because the
				// following query takes too long, he won't run into it again
				set_config('search_last_gc', time(), true);

				// Delete the matches
				//$sql = 'DELETE FROM ' . PRIVMSGS_TABLE . '_swm' . '
				//	WHERE ' . $this->db->sql_in_set('word_id', $sql_in);
				//$this->db->sql_query($sql);
			}
			unset($sql_in);
		}

		if (sizeof($destroy_cache_words))
		{
			// destroy cached search results containing any of the words that are now common or were removed
			$this->destroy_cache(array_unique($destroy_cache_words));
		}

		set_config('search_last_gc', time(), true);
	}

	/**
	* Deletes all words from the index
	*/
	public function delete_index($acp_module, $u_action)
	{
		switch ($this->db->get_sql_layer())
		{
			case 'sqlite':
			case 'sqlite3':
				$this->db->sql_query('DELETE FROM ' . PRIVMSGS_TABLE . '_swl WHERE word_id > 0');
				$this->db->sql_query('DELETE FROM ' . PRIVMSGS_TABLE . '_swm WHERE word_id > 0');
				//$this->db->sql_query('DELETE FROM ' . SEARCH_RESULTS_TABLE . '');
			break;

			default:
				$this->db->sql_query('TRUNCATE TABLE ' . PRIVMSGS_TABLE . '_swl');
				$this->db->sql_query('TRUNCATE TABLE ' . PRIVMSGS_TABLE . '_swm');
				$this->db->sql_query('TRUNCATE TABLE ' . SEARCH_RESULTS_TABLE);
			break;
		}
	}

	/**
	* Returns true if both FULLTEXT indexes exist
	*/
	public function index_created()
	{
		if (!sizeof($this->stats))
		{
			$this->get_stats();
		}

		return ($this->stats['total_words'] && $this->stats['total_matches']) ? true : false;
	}

	/**
	* Returns an associative array containing information about the indexes
	*/
	public function index_stats()
	{
		if (!sizeof($this->stats))
		{
			$this->get_stats();
		}

		return array(
			$this->user->lang['TOTAL_WORDS']		=> $this->stats['total_words'],
			$this->user->lang['TOTAL_MATCHES']	=> $this->stats['total_matches']);
	}

	protected function get_stats()
	{
		$this->stats['total_words']		= $this->db->get_estimated_row_count(PRIVMSGS_TABLE . '_swl');
		$this->stats['total_matches']	= $this->db->get_estimated_row_count(PRIVMSGS_TABLE . '_swm');
	}

	/**
	* Clean up a text to remove non-alphanumeric characters
	*
	* This method receives a UTF-8 string, normalizes and validates it, replaces all
	* non-alphanumeric characters with strings then returns the result.
	*
	* Any number of "allowed chars" can be passed as a UTF-8 string in NFC.
	*
	* @param	string	$text			Text to split, in UTF-8 (not normalized or sanitized)
	* @param	string	$allowed_chars	String of special chars to allow
	* @param	string	$encoding		Text encoding
	* @return	string					Cleaned up text, only alphanumeric chars are left
	*
	* @todo \normalizer::cleanup being able to be used?
	*/
	protected function cleanup($text, $allowed_chars = null, $encoding = 'utf-8')
	{
		static $conv = array(), $conv_loaded = array();
		$words = $allow = array();

		// Convert the text to UTF-8
		$encoding = strtolower($encoding);
		if ($encoding != 'utf-8')
		{
			$text = utf8_recode($text, $encoding);
		}

		$utf_len_mask = array(
			"\xC0"	=>	2,
			"\xD0"	=>	2,
			"\xE0"	=>	3,
			"\xF0"	=>	4
		);

		/**
		* Replace HTML entities and NCRs
		*/
		$text = htmlspecialchars_decode(utf8_decode_ncr($text), ENT_QUOTES);

		/**
		* Load the UTF-8 normalizer
		*
		* If we use it more widely, an instance of that class should be held in a
		* a global variable instead
		*/
		\utf_normalizer::nfc($text);

		/**
		* The first thing we do is:
		*
		* - convert ASCII-7 letters to lowercase
		* - remove the ASCII-7 non-alpha characters
		* - remove the bytes that should not appear in a valid UTF-8 string: 0xC0,
		*   0xC1 and 0xF5-0xFF
		*
		* @todo in theory, the third one is already taken care of during normalization and those chars should have been replaced by Unicode replacement chars
		*/
		$sb_match	= "ISTCPAMELRDOJBNHFGVWUQKYXZ\r\n\t!\"#$%&'()*+,-./:;<=>?@[\\]^_`{|}~\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\xC0\xC1\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";
		$sb_replace	= 'istcpamelrdojbnhfgvwuqkyxz                                                                              ';

		/**
		* This is the list of legal ASCII chars, it is automatically extended
		* with ASCII chars from $allowed_chars
		*/
		$legal_ascii = ' eaisntroludcpmghbfvq10xy2j9kw354867z';

		/**
		* Prepare an array containing the extra chars to allow
		*/
		if (isset($allowed_chars[0]))
		{
			$pos = 0;
			$len = strlen($allowed_chars);
			do
			{
				$c = $allowed_chars[$pos];

				if ($c < "\x80")
				{
					/**
					* ASCII char
					*/
					$sb_pos = strpos($sb_match, $c);
					if (is_int($sb_pos))
					{
						/**
						* Remove the char from $sb_match and its corresponding
						* replacement in $sb_replace
						*/
						$sb_match = substr($sb_match, 0, $sb_pos) . substr($sb_match, $sb_pos + 1);
						$sb_replace = substr($sb_replace, 0, $sb_pos) . substr($sb_replace, $sb_pos + 1);
						$legal_ascii .= $c;
					}

					++$pos;
				}
				else
				{
					/**
					* UTF-8 char
					*/
					$utf_len = $utf_len_mask[$c & "\xF0"];
					$allow[substr($allowed_chars, $pos, $utf_len)] = 1;
					$pos += $utf_len;
				}
			}
			while ($pos < $len);
		}

		$text = strtr($text, $sb_match, $sb_replace);
		$ret = '';

		$pos = 0;
		$len = strlen($text);

		do
		{
			/**
			* Do all consecutive ASCII chars at once
			*/
			if ($spn = strspn($text, $legal_ascii, $pos))
			{
				$ret .= substr($text, $pos, $spn);
				$pos += $spn;
			}

			if ($pos >= $len)
			{
				return $ret;
			}

			/**
			* Capture the UTF char
			*/
			$utf_len = $utf_len_mask[$text[$pos] & "\xF0"];
			$utf_char = substr($text, $pos, $utf_len);
			$pos += $utf_len;

			if (($utf_char >= UTF8_HANGUL_FIRST && $utf_char <= UTF8_HANGUL_LAST)
				|| ($utf_char >= UTF8_CJK_FIRST && $utf_char <= UTF8_CJK_LAST)
				|| ($utf_char >= UTF8_CJK_B_FIRST && $utf_char <= UTF8_CJK_B_LAST))
			{
				/**
				* All characters within these ranges are valid
				*
				* We separate them with a space in order to index each character
				* individually
				*/
				$ret .= ' ' . $utf_char . ' ';
				continue;
			}

			if (isset($allow[$utf_char]))
			{
				/**
				* The char is explicitly allowed
				*/
				$ret .= $utf_char;
				continue;
			}

			if (isset($conv[$utf_char]))
			{
				/**
				* The char is mapped to something, maybe to itself actually
				*/
				$ret .= $conv[$utf_char];
				continue;
			}

			/**
			* The char isn't mapped, but did we load its conversion table?
			*
			* The search indexer table is split into blocks. The block number of
			* each char is equal to its codepoint right-shifted for 11 bits. It
			* means that out of the 11, 16 or 21 meaningful bits of a 2-, 3- or
			* 4- byte sequence we only keep the leftmost 0, 5 or 10 bits. Thus,
			* all UTF chars encoded in 2 bytes are in the same first block.
			*/
			if (isset($utf_char[2]))
			{
				if (isset($utf_char[3]))
				{
					/**
					* 1111 0nnn 10nn nnnn 10nx xxxx 10xx xxxx
					* 0000 0111 0011 1111 0010 0000
					*/
					$idx = ((ord($utf_char[0]) & 0x07) << 7) | ((ord($utf_char[1]) & 0x3F) << 1) | ((ord($utf_char[2]) & 0x20) >> 5);
				}
				else
				{
					/**
					* 1110 nnnn 10nx xxxx 10xx xxxx
					* 0000 0111 0010 0000
					*/
					$idx = ((ord($utf_char[0]) & 0x07) << 1) | ((ord($utf_char[1]) & 0x20) >> 5);
				}
			}
			else
			{
				/**
				* 110x xxxx 10xx xxxx
				* 0000 0000 0000 0000
				*/
				$idx = 0;
			}

			/**
			* Check if the required conv table has been loaded already
			*/
			if (!isset($conv_loaded[$idx]))
			{
				$conv_loaded[$idx] = 1;
				$file = $this->phpbb_root_path . 'includes/utf/data/search_indexer_' . $idx . '.' . $this->php_ext;

				if (file_exists($file))
				{
					$conv += include($file);
				}
			}

			if (isset($conv[$utf_char]))
			{
				$ret .= $conv[$utf_char];
			}
			else
			{
				/**
				* We add an entry to the conversion table so that we
				* don't have to convert to codepoint and perform the checks
				* that are above this block
				*/
				$conv[$utf_char] = ' ';
				$ret .= ' ';
			}
		}
		while (1);

		return $ret;
	}

	/**
	* Removes old entries from the search results table and removes searches with keywords that contain a word in $words.
	*/
	function destroy_cache($words, $authors = false)
	{
		global $db, $cache, $config;

		// clear all searches that searched for the specified words
		if (sizeof($words))
		{
			$sql_where = '';
			foreach ($words as $word)
			{
				$sql_where .= " OR search_keywords " . $db->sql_like_expression($db->get_any_char() . $word . $db->get_any_char());
			}

			$sql = 'SELECT search_key
				FROM ' . SEARCH_RESULTS_TABLE . "
				WHERE search_keywords LIKE '%*%' $sql_where";
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$cache->destroy('_search_results_' . $row['search_key']);
			}
			$db->sql_freeresult($result);
		}

		// clear all searches that searched for the specified authors
		if (is_array($authors) && sizeof($authors))
		{
			$sql_where = '';
			foreach ($authors as $author)
			{
				$sql_where .= (($sql_where) ? ' OR ' : '') . 'search_authors ' . $db->sql_like_expression($db->get_any_char() . ' ' . (int) $author . ' ' . $db->get_any_char());
			}

			$sql = 'SELECT search_key
				FROM ' . SEARCH_RESULTS_TABLE . "
				WHERE $sql_where";
			$result = $db->sql_query($sql);

			while ($row = $db->sql_fetchrow($result))
			{
				$cache->destroy('_search_results_' . $row['search_key']);
			}
			$db->sql_freeresult($result);
		}

		$sql = 'DELETE
			FROM ' . SEARCH_RESULTS_TABLE . '
			WHERE search_time < ' . (time() - $config['search_store_results']);
		$db->sql_query($sql);
	}

	/**
	* Retrieves cached search results
	*
	* @param string $search_key		an md5 string generated from all the passed search options to identify the results
	* @param int	&$result_count	will contain the number of all results for the search (not only for the current page)
	* @param array 	&$id_ary 		is filled with the ids belonging to the requested page that are stored in the cache
	* @param int 	&$start			indicates the first index of the page
	* @param int 	$per_page		number of ids each page is supposed to contain
	* @param string $sort_dir		is either a or d representing ASC and DESC
	*
	* @return int SEARCH_RESULT_NOT_IN_CACHE or SEARCH_RESULT_IN_CACHE or SEARCH_RESULT_INCOMPLETE
	*/
	function obtain_ids($search_key, &$result_count, &$id_ary, &$start, $per_page, $sort_dir)
	{
		global $cache;

		if (!($stored_ids = $cache->get('_search_results_' . $search_key)))
		{
			// no search results cached for this search_key
			return SEARCH_RESULT_NOT_IN_CACHE;
		}
		else
		{
			$result_count = $stored_ids[-1];
			$reverse_ids = ($stored_ids[-2] != $sort_dir) ? true : false;
			$complete = true;

			// Change start parameter in case out of bounds
			if ($result_count)
			{
				if ($start < 0)
				{
					$start = 0;
				}
				else if ($start >= $result_count)
				{
					$start = floor(($result_count - 1) / $per_page) * $per_page;
				}
			}

			// change the start to the actual end of the current request if the sort direction differs
			// from the dirction in the cache and reverse the ids later
			if ($reverse_ids)
			{
				$start = $result_count - $start - $per_page;

				// the user requested a page past the last index
				if ($start < 0)
				{
					return SEARCH_RESULT_NOT_IN_CACHE;
				}
			}

			for ($i = $start, $n = $start + $per_page; ($i < $n) && ($i < $result_count); $i++)
			{
				if (!isset($stored_ids[$i]))
				{
					$complete = false;
				}
				else
				{
					$id_ary[] = $stored_ids[$i];
				}
			}
			unset($stored_ids);

			if ($reverse_ids)
			{
				$id_ary = array_reverse($id_ary);
			}

			if (!$complete)
			{
				return SEARCH_RESULT_INCOMPLETE;
			}
			return SEARCH_RESULT_IN_CACHE;
		}
	}

	/**
	* Caches post/topic ids
	*
	* @param string $search_key		an md5 string generated from all the passed search options to identify the results
	* @param string $keywords 		contains the keywords as entered by the user
	* @param array	$author_ary		an array of author ids, if the author should be ignored during the search the array is empty
	* @param int 	$result_count	contains the number of all results for the search (not only for the current page)
	* @param array	&$id_ary 		contains a list of post or topic ids that shall be cached, the first element
	* 	must have the absolute index $start in the result set.
	* @param int	$start			indicates the first index of the page
	* @param string $sort_dir		is either a or d representing ASC and DESC
	*
	* @return null
	*/
	function save_ids($search_key, $keywords, $author_ary, $result_count, &$id_ary, $start, $sort_dir)
	{
		global $cache, $config, $db, $user;

		$length = min(sizeof($id_ary), $config['search_block_size']);

		// nothing to cache so exit
		if (!$length)
		{
			return;
		}

		$store_ids = array_slice($id_ary, 0, $length);

		// create a new resultset if there is none for this search_key yet
		// or add the ids to the existing resultset
		if (!($store = $cache->get('_search_results_' . $search_key)))
		{
			// add the current keywords to the recent searches in the cache which are listed on the search page
			if (!empty($keywords) || sizeof($author_ary))
			{
				$sql = 'SELECT search_time
					FROM ' . SEARCH_RESULTS_TABLE . '
					WHERE search_key = \'' . $db->sql_escape($search_key) . '\'';
				$result = $db->sql_query($sql);

				if (!$db->sql_fetchrow($result))
				{
					$sql_ary = array(
						'search_key'		=> $search_key,
						'search_time'		=> time(),
						'search_keywords'	=> $keywords,
						'search_authors'	=> ' ' . implode(' ', $author_ary) . ' '
					);

					$sql = 'INSERT INTO ' . SEARCH_RESULTS_TABLE . ' ' . $db->sql_build_array('INSERT', $sql_ary);
					$db->sql_query($sql);
				}
				$db->sql_freeresult($result);
			}

			$sql = 'UPDATE ' . USERS_TABLE . '
				SET user_last_search = ' . time() . '
				WHERE user_id = ' . $user->data['user_id'];
			$db->sql_query($sql);

			$store = array(-1 => $result_count, -2 => $sort_dir);
			$id_range = range($start, $start + $length - 1);
		}
		else
		{
			// we use one set of results for both sort directions so we have to calculate the indizes
			// for the reversed array and we also have to reverse the ids themselves
			if ($store[-2] != $sort_dir)
			{
				$store_ids = array_reverse($store_ids);
				$id_range = range($store[-1] - $start - $length, $store[-1] - $start - 1);
			}
			else
			{
				$id_range = range($start, $start + $length - 1);
			}
		}

		$store_ids = array_combine($id_range, $store_ids);

		// append the ids
		if (is_array($store_ids))
		{
			$store += $store_ids;

			// if the cache is too big
			if (sizeof($store) - 2 > 20 * $config['search_block_size'])
			{
				// remove everything in front of two blocks in front of the current start index
				for ($i = 0, $n = $id_range[0] - 2 * $config['search_block_size']; $i < $n; $i++)
				{
					if (isset($store[$i]))
					{
						unset($store[$i]);
					}
				}

				// remove everything after two blocks after the current stop index
				end($id_range);
				for ($i = $store[-1] - 1, $n = current($id_range) + 2 * $config['search_block_size']; $i > $n; $i--)
				{
					if (isset($store[$i]))
					{
						unset($store[$i]);
					}
				}
			}
			$cache->put('_search_results_' . $search_key, $store, $config['search_store_results']);

			$sql = 'UPDATE ' . SEARCH_RESULTS_TABLE . '
				SET search_time = ' . time() . '
				WHERE search_key = \'' . $db->sql_escape($search_key) . '\'';
			$db->sql_query($sql);
		}

		unset($store);
		unset($store_ids);
		unset($id_range);
	}
}
