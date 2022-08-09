<?php

// Convert PDFTOXML XML to OCR JSON

require_once (dirname(__FILE__) . '/spatial.php');
require_once (dirname(__FILE__) . '/common.php');

//----------------------------------------------------------------------------------------

function parse_xml($filename)
{
	$obj = new stdclass;

	$obj = new stdclass;
	$obj->pages = array();
	$obj->text_bbox = new BBox(0,0,0,0);


	$xml = file_get_contents($filename);
				
	$dom = new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	$page_count = 0;	
				
	$pages = $xpath->query ('//PAGE');
	foreach($pages as $xml_page)
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
	
		$page->width = $attributes['width'];
		$page->height = $attributes['height'];	
	
		$page->bbox = new BBox(0, 0, $page->width, $page->height);
		$page->text_bbox = new BBox($page->width, $page->height, 0, 0);
		
		// images (figures) from born native PDF ---------------------------------------------
		$images = $xpath->query ('IMAGE', $xml_page);
		foreach($images as $image)
		{
			// coordinates
			if ($image->hasAttributes()) 
			{ 
				$attributes = array();
				$attrs = $image->attributes; 
			
				foreach ($attrs as $i => $attr)
				{
					$attributes[$attr->name] = $attr->value; 
				}
			}
		
			// ignore block x=0, y=0 as this is the whole page(?)
			if (($attributes['x'] != 0) && ($attributes['y'] != 0))
			{
		
				// save
				$image_obj = new stdclass;
				$image_obj->bbox = new BBox(
				$attributes['x'], 
				$attributes['y'],
				$attributes['x'] + $attributes['width'],
				$attributes['y'] + $attributes['height']
				);
		
				$image_obj->href = $attributes['href'];
		
				$page->images[] = $image_obj;
			}		
		}

		// text from born native PDF ---------------------------------------------------------
	
		// Get blocks using PDFXML structure, note that we ignore lines as we want blocks 
		// of text to process to find entities	
		$line_counter = 0; // global line counter
		$blocks = $xpath->query ('BLOCK', $xml_page);
		foreach($blocks as $block_tag)
		{
			$block = new stdclass;
			$block->type = 'block';
			$block->bbox = new BBox($page->width, $page->height, 0, 0); 
			$block->tokens = array();
			$block->text_strings = array();

			$block->lines = array();
		
			// Get lines of text
			$lines = $xpath->query ('TEXT', $block_tag);
		
			foreach($lines as $line_tag)
			{
				// coordinates
				if ($line_tag->hasAttributes()) 
				{ 
					$attributes = array();
					$attrs = $line_tag->attributes; 
		
					foreach ($attrs as $i => $attr)
					{
						$attributes[$attr->name] = $attr->value; 
					}
				}
			
				$line = new stdclass;
				$line->type = 'line';			
				$line->id = $line_counter++;
	
				$line->bbox = new BBox(
					$attributes['x'], 
					$attributes['y'],
					$attributes['x'] + $attributes['width'],
					$attributes['y'] + $attributes['height']
					);
		
				$block->bbox->merge($line->bbox);
	
				// text	
				$line->text_strings = array();

				$nc = $xpath->query ('TOKEN', $line_tag);
				
				foreach($nc as $n)
				{
					// coordinates
					if ($n->hasAttributes()) 
					{ 
						$attributes = array();
						$attrs = $n->attributes; 
			
						foreach ($attrs as $i => $attr)
						{
							$attributes[$attr->name] = $attr->value; 
						}
					}
		
					$token = new stdclass;
					$token->type = 'token';
				
					$token->bbox = new BBox(
						$attributes['x'], 
						$attributes['y'],
						$attributes['x'] + $attributes['width'],
						$attributes['y'] + $attributes['height']
						);				
				
					$token->bold 		= $attributes['bold'] == 'yes' ? true : false;
					$token->italic		= $attributes['italic'] == 'yes' ? true : false;
					$token->font_size 	= $attributes['font-size'];
					
					if (isset($attributes['font-name']))
					{
						$token->font_name 	= $attributes['font-name'];		
					}
					if (isset($n->firstChild->nodeValue))
					{
						$token->text 		= $n->firstChild->nodeValue;
						$line->text_strings[] = $token->text;
						$block->tokens[] = $token;						
						$block->text_strings[] = $token->text;						
					}					
				
					$token->rotation 	= $attributes['rotation'] == '1' ? true : false;
					$token->angle 		= $attributes['angle'];
						
				}
			
				$line->text = join(' ', $line->text_strings);
				unset($line->text_strings);
			
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
	

		$obj->text_bbox->merge($page->text_bbox);
		
		$obj->pages[] = $page;
		
		$page_count++;
		
		
	}
	
	return $obj;
}

$filename = 'zt01956p080.xml';
$filename = 'liu2009ref13009-7843.xml';

$obj = parse_xml($filename);

echo document_to_html($obj);


?>
