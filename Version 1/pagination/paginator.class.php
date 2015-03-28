<?php

/**
*
* 
* @filename:		    paginator.class.php
* @filetype:		    PHP
* @description:	    This pagination class goes with any type of query. It
*                   can be easily customerize with CSS cause its display 
*                   is in three types: links (<a></a>), list items <ul>
*                   </ul> and select input (<select></select>).
* @version:			    16.4.13
* @author(s):			  JAY & AMA
* @authoremail(s):  evolutioneerbeyond@yahoo.com & j.ilukhor@gmail.com
* @twitter:         @deusex0
*                   @One_Oracle
* @lastmodified:    01/04/2013 04:27:47
* @license:         http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
* @copyright:       Copyright (c) 2013 Jencube
* @usage: 
* @supportfile(s):  mysql.class.php
*  
* 
*/

class Pagination {
	var $listPerPage = 25;
	var $totalLists;
	var $currentPage;
	var $totalNumPages;
	var $pageNum;
	var $startPage;
	var $pageLink;
	var $type;
	var $prevBtn = TRUE;
	var $nextBtn = TRUE;
	var $firstBtn = FALSE;
	var $lastBtn = FALSE;
	var $lastBtnTitle = "Last";
	var $prevBtnTitle = "Previous";
	var $nextBtnTitle = "Next";
	var $firstBtnTitle = "First";
	private $loop;

	public function __construct( $data = NULL ) {
		if( !empty( $data['list_per_page'] ) ) {
			$this->listPerPage = $data['list_per_page'];
		}
		$this->pageNum = ( !empty( $data['current_page'] ) )? $data['current_page'] : '1';
		$this->currentPage = $this->pageNum;
		$this->pageNum -= 1;
		$this->startPage = $this->pageNum * $this->listPerPage;
		$this->pageLink = $data['page_url'];
		$this->totalLists = $data['total_query'];
		$this->type = ( !empty( $data['type'] ) )? $data['type'] : 'link';
		if ( !empty( $data['last'] ) && $this->lastBtn ) {
			$this->lastBtnTitle = $data['last'];
		}

		if ( !empty( $data['previous'] ) && $this->prevBtn ) {
			$this->prevBtnTitle = $data['previous'];
		}

		if ( !empty( $data['next'] ) && $this->nextBtn ) {
			$this->nextBtnTitle = $data['next'];
		}

		if ( !empty( $data['first'] ) && $this->firstBtn ) {
			$this->firstBtnTitle = $data['first'];
		}
	}

    public function load( $params ) {
      // store data
      $this->__construct( $params );
    }

	public function display(){
		$this->totalNumPages = ceil( $this->totalLists / $this->listPerPage );
		$this->loop = $this->paging( $this->currentPage, $this->totalNumPages );

		switch( $this->type ) {
			case "link":
				return $this->links();
			break;
			case "list":
				return $this->lists();
			break;
			case "input":
				return $this->input();
			break;			
		}

	}

	private function paging( $currentPage, $noofPages ) {
		$loop = array();
		if ( $currentPage >= 7 ) {
			$loop['startLoop'] = $currentPage - 3;
			if ( $noofPages > ( $currentPage + 3 ) )
				$loop['endLoop'] = $currentPage + 3;
			else if ( ( $currentPage <= $noofPages ) && $currentPage > ( $noofPages - 6 ) ) {
				$loop['startLoop'] = $noofPages - 6;
				$loop['endLoop'] = $noofPages;
			} else {
				$loop['endLoop'] = $noofPages;
			}
		} else {
			$loop['startLoop'] = 1;
			if ( $noofPages > 7 )
				$loop['endLoop'] = 7;
			else
				$loop['endLoop'] = $noofPages;
		}
		return $loop;
	}

	private function lists(){

		$datas = '<ul id="pagination">';

        // Enabling the first button
        if ( $this->firstBtn && $this->currentPage > 1 ) {
            $datas .= '<li data-url="1" class="active">' . $this->firstBtnTitle . '</li>';
        } else if ( $this->firstBtn ) {
            $datas .= '<li data-url="1" class="inactive">' . $this->firstBtnTitle . '</li>';
        }

        // Enabling the previous button
        if ( $this->prevBtn && $this->currentPage > 1 ) {
            $prev = $this->currentPage - 1;
            $datas .= '<li data-url="' . $prev . '" class="active">' . $this->prevBtnTitle . '</li>';
        } else if ( $this->prevBtn ) {
            $datas .= '<li class="inactive">' . $this->prevBtnTitle . '</li>';
        }

        for( $i = $this->loop['startLoop']; $i <= $this->loop['endLoop']; $i++ ) {
            if ( $this->currentPage == $i )
                $datas .= '<li data-url="' . $i . '" class="active current">' . $i . '</li>';
            else
                $datas .= '<li data-url="' . $i . '" class="active">' . $i . '</li>';
        }

        // Enabling the next button
        if ( $this->nextBtn && $this->currentPage < $this->totalNumPages ) {
            $next = $this->currentPage + 1;
            $datas .= '<li data-url="' . $next . '" class="active">' . $this->nextBtnTitle . '</li>';
        } else if ( $this->nextBtn ) {
            $datas .= '<li class="inactive">' . $this->nextBtnTitle . '</li>';
        }

        // Enabling the last button
        if ( $this->lastBtn && $this->currentPage < $this->totalNumPages ){
            $datas .= '<li data-url="' . $this->totalNumPages . '" class="active">' . $this->lastBtnTitle . '</li>';
        } else if ( $this->lastBtn ) {
            $datas .= '<li data-url="' . $this->totalNumPages . '" class="inactive">' . $this->lastBtnTitle . '</li>';
        }
        $datas .= '</ul>';
        
        return $datas;
	}

	private function links(){
		$datas = '<div id="pagination">';

        // Enabling the first button
        if ( $this->firstBtn && $this->currentPage > 1 ) {
            $datas .= '<a href="' . $this->pageLink . '?page=1" class="active">' . $this->firstBtnTitle . '</a>';
        } else if ( $this->firstBtn ) {
            $datas .= '<a href="' . $this->pageLink . '?page=1" class="inactive">' . $this->firstBtnTitle . '</a>';
        }

        // Enabling the previous button
        if ( $this->prevBtn && $this->currentPage > 1 ) {
            $prev = $this->currentPage - 1;
            $datas .= '<a href="' . $this->pageLink . '?page=' . $prev . '" class="active">'.$this->prevBtnTitle.'</a>';
        } else if ( $this->prevBtn ) {
            $datas .= '<a href="#" class="inactive">'.$this->prevBtnTitle.'</a>';
        }

        for( $i = $this->loop['startLoop']; $i <= $this->loop['endLoop']; $i++ ) {
            if ( $this->currentPage == $i )
                $datas .= '<a href="'.$this->pageLink.'?page='.$i.'" class="active current">'.$i.'</a>';
            else
                $datas .= '<a href="'.$this->pageLink.'?page='.$i.'" class="active">'.$i.'</a>';
        }

        // Enabling the next button
        if ( $this->nextBtn && $this->currentPage < $this->totalNumPages ) {
            $next = $this->currentPage + 1;
            $datas .= '<a href="' . $this->pageLink . '?page=' . $next . '" class="active">' . $this->nextBtnTitle . '</a>';
        } else if ( $this->nextBtn ) {
            $datas .= '<a href="#" class="inactive">' . $this->nextBtnTitle . '</a>';
        }

        // Enabling the last button
        if ( $this->lastBtn && $this->currentPage < $this->totalNumPages ) {
            $datas .= '<a href="' . $this->pageLink . '?page=' . $this->totalNumPages . '" class="active">'.$this->lastBtnTitle.'</a>';
        } else if ( $this->lastBtn ) {
            $datas .= '<a href="' . $this->pageLink . '?page=' . $this->totalNumPages . '" class="inactive">'.$this->lastBtnTitle.'</a>';
        }
        $datas .= '</div>';
        
        return $datas;
	}

	private function input(){
		$datas = "<span class=\"paginator\">Page:</span>
					<select id=\"pagination\" onchange=\"window.location='" . $this->pageLink . "?page='+this[this.selectedIndex].value;return false\">";

		for( $i = $this->loop['startLoop']; $i <= $this->loop['endLoop']; $i++ ) {
			if ( $this->currentPage == $i )
                $datas .= "<option value=\"$i\" selected>$i</option>\n";
            else
                $datas .= "<option value=\"$i\">$i</option>\n";
		}

		$datas .= "</select>\n";

		return $datas;
	}

	public function display_list()
	{
		$options = '';
		$numPerPage = array( 10, 25, 50, 100, 'All' );
		foreach( $numPerPage as $keyData )	
			$options .= ( $keyData == $this->listPerPage )? "<option value=\"$keyData\" selected>$keyData</option>\n":"<option value=\"$keyData\">$keyData</option>\n";
		return "<span class=\"paginate\">Items per page:</span>
				<select class=\"paginate\" onchange=\"window.location='" . $this->pageLink . "?p='+this[this.selectedIndex].value;return false\">$options</select>\n";
	}

}

?>