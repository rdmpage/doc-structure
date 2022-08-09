<?php

// Common model of document layout

require_once (dirname(__FILE__) . '/spatial.php');

//----------------------------------------------------------------------------------------
function document_to_text($obj)
{
	foreach ($obj->pages as $page)
	{
		foreach ($page->blocks as $block)
		{
			if (isset($block->text))
			{
				echo $block->text . "\n\n";
			}
		}
	}
}

//----------------------------------------------------------------------------------------
function document_to_html($obj)
{
	$html = '';

	$html .= '<html>
	<head>
	<style>
		body {
			background:rgb(228,228,228);
			margin:0;
			padding:0;
		}
	
		.page {
			background-color:white;
			position:relative;
			margin: 0 auto;
			/* border:1px solid black; */
			margin-bottom:1em;
			margin-top:1em;	
			box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2), 0 6px 20px 0 rgba(0, 0, 0, 0.19);
		}
	
		.box {
			position:absolute;
			border:1px dashed black;			
		}
	
		/* block */
		.block {
			position:absolute;
			background-color:rgba(255,255,0,0.2);
			border:1px solid rgba(192,192,192);		
		}
	
		/* figure */
		.image {
			position:absolute;
			background-color:rgba(255,53,184,0.2);
			/* Can invert image if needed https://stackoverflow.com/a/13325820/9684 */
			/* filter: invert(1); */
		}
	
		/* table */
		.table {
			position:absolute;
			background-color:rgba(0,0,255,0.2);
		}
	
		/* line */
		.line {
			position:absolute;
			background-color:green;
			opacity:0.4;
		}
	
		/* visible text */
		.token {
			position:absolute;
		}	
	
		.token-text {
			rgba(19,19,19,1);
			vertical-align:baseline;
			white-space:nowrap;
		}	

	</style>
	</head>
	<body>';	
	
	$width = $obj->pages[0]->bbox->maxx - $obj->pages[0]->bbox->minx;
	
	// scale output to be a sensible width
	$scale= 600/$width;
	
	foreach ($obj->pages as $page)
	{
		// page
		$x = $page->bbox->minx;
		$y = $page->bbox->miny;
		$w = $page->bbox->maxx - $page->bbox->minx;
		$h = $page->bbox->maxy - $page->bbox->miny;
	
		$x *= $scale;
		$y *= $scale;
		$w *= $scale;
		$h *= $scale;
	
	
		$html .= '<div class="page" style="width:' . $w . 'px;height:' . $h . 'px;">'  . "\n";
		
		if (isset($page->imageUrl))
		{
			$html .= '<img src="' . $page->imageUrl . '" width="' .  $w . '">' . "\n";
		}
		
		// text box for whole document
		{
			$x = $obj->text_bbox->minx;
			$y = $obj->text_bbox->miny;
			$w = $obj->text_bbox->maxx - $obj->text_bbox->minx;
			$h = $obj->text_bbox->maxy - $obj->text_bbox->miny;
	
			$x *= $scale;
			$y *= $scale;
			$w *= $scale;
			$h *= $scale;
		
			$html .= '<div class="box" style="left:' . $x . 'px;top:' . $y . 'px;width:' . $w . 'px;height:' . $h . 'px;"></div>'  . "\n";
		}
		
		// text box for this page
		{
			$x = $page->text_bbox->minx;
			$y = $page->text_bbox->miny;
			$w = $page->text_bbox->maxx - $page->text_bbox->minx;
			$h = $page->text_bbox->maxy - $page->text_bbox->miny;
	
			$x *= $scale;
			$y *= $scale;
			$w *= $scale;
			$h *= $scale;
		
			$html .= '<div class="box" style="left:' . $x . 'px;top:' . $y . 'px;width:' . $w . 'px;height:' . $h . 'px;"></div>'  . "\n";
		}		
		
		// blocks on the page
		foreach ($page->blocks as $block)
		{
			$x = $block->bbox->minx;
			$y = $block->bbox->miny;
			$w = $block->bbox->maxx - $block->bbox->minx;
			$h = $block->bbox->maxy - $block->bbox->miny;
			
			$x *= $scale;
			$y *= $scale;
			$w *= $scale;
			$h *= $scale;
				
			$html .= '<div class="' . $block-> type . '" style="' 
				. 'left:' 	. $x . 'px;'
				. 'top:' 	. $y . 'px;'
				. 'width:' 	. $w . 'px;'
				. 'height:' . $h . 'px;'
				. '">'  . "\n";
				
			// lines of text within block
			if (isset($block->lines))
			{
				foreach ($block->lines as $line)
				{
					$x = $line->bbox->minx - $block->bbox->minx;
					$y = $line->bbox->miny - $block->bbox->miny;
					$w = $line->bbox->maxx - $line->bbox->minx;
					$h = $line->bbox->maxy - $line->bbox->miny;
			
					$x *= $scale;
					$y *= $scale;
					$w *= $scale;
					$h *= $scale;
				
					$html .= '<div class="line" style="' 
						. 'left:' 	. $x . 'px;'
						. 'top:' 	. $y . 'px;'
						. 'width:' 	. $w . 'px;'
						. 'height:' . $h . 'px;'
						. '">'  . "\n";
					
					// font 
					//if (isset($line->capheight))
					{
						//$html .= '<span style="background-color:black;color:white">' . ($line->baseline - $line->capheight) . '</span>';
						//$html .= '<span style="background-color:black;color:white">' . ($line->bbox->maxy  - $line->bbox->miny) . '</span>';
						//$html .= '<span style="background-color:black;color:white">' . $line->text . '</span>';
					}

					$html .= '</div>'  . "\n";				
				}
			}				
			$html .= '</div>'  . "\n";				
		}
		
		// Images on a page (e.g., figures)
		foreach ($page->images as $block)
		{
			$x = $block->bbox->minx;
			$y = $block->bbox->miny;
			$w = $block->bbox->maxx - $block->bbox->minx;
			$h = $block->bbox->maxy - $block->bbox->miny;
			
			$x *= $scale;
			$y *= $scale;
			$w *= $scale;
			$h *= $scale;
				
			$html .= '<div class="image" style="' 
				. 'left:' 	. $x . 'px;'
				. 'top:' 	. $y . 'px;'
				. 'width:' 	. $w . 'px;'
				. 'height:' . $h . 'px;'
				. '">'  . "\n";
			$html .= '</div>' . "\n";
		
		}
		
		// Tables
		if (isset($page->tables))
		{
			foreach ($page->tables as $block)
			{
				$x = $block->bbox->minx;
				$y = $block->bbox->miny;
				$w = $block->bbox->maxx - $block->bbox->minx;
				$h = $block->bbox->maxy - $block->bbox->miny;
			
				$x *= $scale;
				$y *= $scale;
				$w *= $scale;
				$h *= $scale;
				
				$html .= '<div class="table" style="' 
					. 'left:' 	. $x . 'px;'
					. 'top:' 	. $y . 'px;'
					. 'width:' 	. $w . 'px;'
					. 'height:' . $h . 'px;'
					. '">'  . "\n";
				$html .= '</div>' . "\n";
		
			}
		}
				
		
		$html .= '</div>' . "\n";
	}
	
	$html .= '</body></html>';	
	return $html;
}

//----------------------------------------------------------------------------------------
function document_to_json($obj)
{
	$output = new stdclass;
	$output->text_bbox = $obj->text_bbox->toArray();
	$output->pages = array();
		
	foreach ($obj->pages as $page)
	{
		$output_page = new stdclass;
		
		$output_page->bbox 		= $page->bbox->toArray();
		$output_page->text_bbox = $page->text_bbox->toArray();
		
		$output_page->rect = $page->bbox->toRectangle()->toArray();
		$output_page->text_rect = $page->text_bbox->toRectangle()->toArray();

		$output_page->blocks = array();
				
		// blocks on the page
		foreach ($page->blocks as $block)
		{		
			$output_block = new stdclass;			
			$output_block->bbox = $block->bbox->toArray();
			
			$output_block->rect = $block->bbox->toRectangle()->toArray();			
			
			if (isset($block->text))
			{
				$output_block->text = $block->text;
			}
								
			$output_block->lines = array();
				
			// lines of text within block
			if (isset($block->lines))
			{
				foreach ($block->lines as $line)
				{
					$output_line = new stdclass;
					$output_line->bbox = $line->bbox->toArray();
					
					$output_line->rect = $line->bbox->toRectangle()->toArray();			
					
					if (isset($line->text))
					{
						$output_line->text = $line->text;
					}
					
					
					$output_block->lines[] = $output_line;					
				}
			}
			
			$output_page->blocks[] = $output_block;				
		}
		
		/*
		// Images on a page (e.g., figures)
		foreach ($page->images as $block)
		{
			$x = $block->bbox->minx;
			$y = $block->bbox->miny;
			$w = $block->bbox->maxx - $block->bbox->minx;
			$h = $block->bbox->maxy - $block->bbox->miny;
			
			$x *= $scale;
			$y *= $scale;
			$w *= $scale;
			$h *= $scale;
				
			$html .= '<div class="image" style="' 
				. 'left:' 	. $x . 'px;'
				. 'top:' 	. $y . 'px;'
				. 'width:' 	. $w . 'px;'
				. 'height:' . $h . 'px;'
				. '">'  . "\n";
			$html .= '</div>' . "\n";
		
		}
		
		// Tables
		if (isset($page->tables))
		{
			foreach ($page->tables as $block)
			{
				$x = $block->bbox->minx;
				$y = $block->bbox->miny;
				$w = $block->bbox->maxx - $block->bbox->minx;
				$h = $block->bbox->maxy - $block->bbox->miny;
			
				$x *= $scale;
				$y *= $scale;
				$w *= $scale;
				$h *= $scale;
				
				$html .= '<div class="table" style="' 
					. 'left:' 	. $x . 'px;'
					. 'top:' 	. $y . 'px;'
					. 'width:' 	. $w . 'px;'
					. 'height:' . $h . 'px;'
					. '">'  . "\n";
				$html .= '</div>' . "\n";
		
			}
		}
		*/
				
		$output->pages[] = $output_page;
		
	}
	
	echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

?>
