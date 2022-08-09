<?php

// Parse DJVU XML and extract text and tokens from each page,

error_reporting(E_ALL);

require_once('spatial.php');
require_once('common.php');

//----------------------------------------------------------------------------------------
function clean_xml($xml)
{
	$xml = str_replace("&#31;", "", $xml);
	$xml = str_replace("&#11;", "", $xml);
	
	return $xml;
}

//----------------------------------------------------------------------------------------
function parse_djvu($filename)
{
	$source_id = '';

	$obj = new stdclass;

	$obj = new stdclass;
	$obj->pages = array();
	$obj->text_bbox = null;

	$xml = file_get_contents($filename);
				
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$page_count = 0;	
	$line_counter = 0;
				
	foreach($xpath->query ('//OBJECT') as $xml_page)
	{
		// page level
		$page = new stdclass;	
		$page->type = 'page';
		$page->blocks = array();
		$page->images = array();
		
		// coordinates
		if ($xml_page->hasAttributes()) 
		{ 
			$attributes = array();
			$attrs = $xml_page->attributes; 
		
			foreach ($attrs as $i => $attr)
			{
				$attributes[$attr->name] = $attr->value; 
			}
		}
		
		// try and get link to page image
		if (isset($attributes['data']))
		{
			if (preg_match('/\/derive\/(?<id>[^\/]+)\//', $attributes['data'], $m))
			{
				$source_id = $m['id'];				
				$page->imageUrl = 'https://archive.org/download/' . $source_id . '/page/n' . $page_count . '.jpg';
			}
		}
	
		$page->width = $attributes['width'];
		$page->height = $attributes['height'];	
		
		// bounding box of page
		$page->bbox = new BBox(0, 0, $page->width, $page->height);
				
		// bounding box of page contents
		$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
		
		//$page->tokens = array();
	
		$regions = $xpath->query ('HIDDENTEXT/PAGECOLUMN/REGION', $xml_page);

		foreach($regions as $region)
		{
			$paragraphs = $xpath->query ('PARAGRAPH', $region);
			
			foreach ($paragraphs as $paragraph)
			{			
				$block = new stdclass;
				$block->type = 'block';
				$block->bbox = new BBox($page->width, $page->height, 0, 0); 
				
				$block->tokens = array();
				$block->text_strings = array();

				$block->lines = array();
			
				$lines = $xpath->query ('LINE', $paragraph);
				
				foreach ($lines as $line_tag)
				{
					$line = new stdclass;
					$line->type = 'line';			
					$line->id = $line_counter++;	
					$line->bbox = new BBox($page->width, $page->height, 0, 0); 
					$line->tokens = array();
					$line->text_strings = array();
					
					// Font info
					$line->baseline = $page->bbox->maxy;
					$line->capheight = 0;
					$line->descender = $page->bbox->maxy;
					$line->ascender = 0;						
								
					$words = $xpath->query ('WORD', $line_tag);
					
					foreach ($words as $word)
					{
					
						// attributes
						if ($word->hasAttributes()) 
						{ 
							$attributes = array();
							$attrs = $word->attributes; 

							foreach ($attrs as $i => $attr)
							{
								$attributes[$attr->name] = $attr->value; 
							}
						}
	
						$coords = explode(",", $attributes['coords']);
					
						$token = new stdclass;
						$token->type = 'token';
				
						$token->bbox = new BBox(
							$coords[0], 
							$coords[3],
							$coords[2],
							$coords[1]
							);			
				
						$token->text = $word->firstChild->nodeValue;
						
						$line->tokens[] = $token;
						$line->text_strings[] = $token->text;

						$block->tokens[] = $token;		
						$block->text_strings[] = $token->text;
													
						// estmate font dimensions
						if (preg_match('/[tdfhklb]/', $token->text))
						{
							$line->ascender = max($line->ascender, $token->bbox->miny);
							$line->baseline = min($line->baseline, $token->bbox->maxy);
						}

						if (preg_match('/[qypgj]/', $token->text))
						{
							$line->descender = min($line->descender, $token->bbox->maxy);
						}

						if (preg_match('/[A-Z0-9]/', $token->text))
						{
							$line->capheight = max($line->capheight, $token->bbox->miny);
							$line->baseline = min($line->baseline, $token->bbox->maxy);
						}
						
						$line->bbox->merge($token->bbox);

					}
					
					$line->text = join(' ', $line->text_strings);
					unset($line->text_strings);
										
					//$page->tokens[] = $token;
					
					$block->bbox->merge($line->bbox);
					
					$block->lines[] = $line;
					
				}
			
				// Grow the page bounding box
				$page->text_bbox->merge($block->bbox);
		
				// Get text for this block and cleanup
				$block->text = join(' ', $block->text_strings);
				unset($block->text_strings);
		
				// Add block to this page
				$page->blocks[] = $block;
			
			}

		}

		//print_r($page);
		
		if (!$obj->text_bbox)
		{
			$obj->text_bbox = $page->text_bbox;
		}
		else
		{
			$obj->text_bbox->merge($page->text_bbox);
		}		
		
		$obj->pages[] = $page;
		
		$page_count++;
	}	
	
	return $obj;
}


$filename = 'PMC3406453.xml';
$filename = 'blumea-0006-5196-59-006-009_djvu.xml';



$obj = parse_djvu($filename);

echo document_to_html($obj);

//echo document_to_text($obj);





?>
