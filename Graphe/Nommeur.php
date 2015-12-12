<?php
/*
 * Copyright (c) 2015 Guillaume Outters
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace eu_outters_guillaume\Util\Graphe;

class Nommeur
{
	const INF_SUP_OU_MOINS = '<>-';
	const INF = '<';
	const MOINS = '-';
	
	public $mode = self::INF_SUP_OU_MOINS;
	
	public function inverse($quoi)
	{
		switch($this->mode)
		{
			case self::INF:
				return substr($quoi, 0, 1) == '<' ? substr($quoi, 1) : '<'.$quoi;
			case self::INF_SUP_OU_MOINS:
		if(substr($quoi, 0, 1) == '<')
			return substr($quoi, 1).'>';
		else if(substr($quoi, -1) == '>')
			return '<'.substr($quoi, 0, -1);
				break;
		}
		
		return substr($quoi, 0, 1) == '-' ? substr($quoi, 1) : '-'.$quoi;
	}
}

?>
