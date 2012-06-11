<?php defined('SYSPATH') or die('No direct script access.');
class Kohana_Pagination {
    protected $config = array(
            'current_page'      => array('source' => 'query_string', 'key' => 'page'),
            'total_items'       => 0,
            'items_per_page'    => 10,
            'view'              => 'pagination/basic',
            'auto_hide'         => TRUE,
            'first_page_in_url' => FALSE,
        ),
        $current_page,
        $total_items,
        $items_per_page,
        $total_pages,
        $current_first_item,
        $current_last_item,
        $previous_page,
        $next_page,
        $first_page,
        $last_page,
        $offset;

	public static function factory(array $config = array()) {
		return new Pagination($config);
	}

	public function __construct(array $config = array()) {
        $this->config = $this->config_group('default') + $this->config;
		$this->setup($config);
	}

	public function config_group($group = 'default') {
		$config_file = Kohana::config('pagination');
		$config['group'] = (string) $group;
		while (isset($config['group']) && isset($config_file->$config['group'])) {
			$group = $config['group'];
			unset($config['group']);
			$config += $config_file->$group;
		}
		unset($config['group']);
		return $config;
	}

	public function setup(array $config = array()) {
		if (isset($config['group'])) {
			$config = $config + $this->config_group($config['group']);
		}
		$this->config = $config + $this->config;

		if ($this->current_page === null || isset($config['current_page'])
			|| isset($config['total_items']) || isset($config['items_per_page'])) {
            if (isset($this->config['current_page']['page'])  &&
                ! empty($this->config['current_page']['page'])) {
				$this->current_page = (int) Arr::path($this->config,'current_page.page');
			} else {
				switch ($this->config['current_page']['source']) {
					case 'query_string':
					case 'mixed':
                        $this->current_page = Request::current()
                            ->query(Arr::path($this->config,'current_page.key'));
                        if (empty($this->current_page))
                            $this->current_page = 1;
						break;
					case 'route':
                        $this->current_page = (int) Request::current()
                            ->param(Arr::path($this->config,'current_page.key'), 1);
						break;
				}
			}

			// Calculate and clean all pagination variables
			$this->total_items        = (int) max(0, Arr::get($this->config,'total_items'));
			$this->items_per_page     = (int) max(1, Arr::get($this->config,'items_per_page'));
			$this->total_pages        = (int) floor($this->total_items / $this->items_per_page);
			$this->current_page       = (int) min(max(1, $this->current_page), max(1, $this->total_pages));
			$this->current_first_item = (int) min((($this->current_page - 1) * $this->items_per_page) + 1, $this->total_items);
			$this->current_last_item  = (int) min($this->current_first_item + $this->items_per_page - 1, $this->total_items);
            $this->previous_page      = ($this->current_page > 1) ? $this->current_page - 1 : false;
            $this->next_page          = ($this->current_page < $this->total_pages) ? $this->current_page + 1 : false;
            $this->first_page         = ($this->current_page === 1) ? false : 1;
            $this->last_page          = ($this->current_page >= $this->total_pages) ? false : $this->total_pages;
            $this->offset             = (int) (($this->current_page - 1) * $this->items_per_page);
		}
		return $this;
	}

	public function url($page = 1) {
		$page = max(1, (int) $page);
		if ($page === 1 && Arr::get($this->config,'first_page_in_url', false)) {
			$page = null;
		}
        $url = '#';
        if ($page) {
            switch (Arr::path($this->config,'current_page.source')) {
                case 'query_string':
                    $url = Request::current()->urL()
                        . URL::query(array($this->config['current_page']['key'] => $page), false);
                    break;
                case 'route':
                    $url = URL::site(Request::current()
                            ->uri(array(Arr::path($this->config,'current_page.key') => $page)))
                        . URL::query(null, false);
                    break;
                case 'mixed':
                    $url = URL::site(Request::detect_uri())
                        . URL::query(array(Arr::path($this->config,'current_page.key') => $page), false);
                    break;
            }
        }
		return $url;
	}
	public function valid_page($page) {
        if (!Validate::digit($page))
            return FALSE;
		return $page > 0 AND $page <= $this->total_pages;
	}
	public function render($view = NULL) {
        $view_data = '';
        if (!(Arr::get($this->config,'auto_hide') === true && $this->total_pages <= 1)) {
            if ($view === NULL) {
                $view = Arr::get($this->config,'view');
            }
            if (!$view instanceof Kohana_View) {
                $view = View::factory($view);
            }
            $view_data = $view
                ->set(get_object_vars($this))->set('page', $this)
                ->render();
        }
        return $view_data;
	}

	public function __toString() {
		return $this->render();
	}

	public function __get($key) {
		return isset($this->$key) ? $this->$key : NULL;
	}

	public function __set($key, $value) {
		$this->setup(array($key => $value));
	}
}
