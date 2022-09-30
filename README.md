# Document Structure

Experiments on extracting document structure from DjVu XML, hOCR, pdftoxml XML and more. Ultimate goal is to develop tools to recognise document elements to help extract articles from BHL, as well as individual elements within articles.

## Document markup

### ABBYY

ABBYY XML markup is huge but comprehensive, and includes figures and tables.

### DjVu

DjVu XML does not include information on figures. 

### hOCR

hOCR is the default output for InternetArchive, now that it has switched to Tesseract as its OCR engine.

### Plazi

Plazi XML gives coordinates for some elements in the form `box[minx, maxx, miny, maxy]` but lacks information on the full page size. These coords are w.r.t. to a single page, and the page number is an attribute of the element.

It appears that Plazi use [ICEpdf](http://www.icesoft.org/java/projects/ICEpdf/overview.jsf) as their PDF tool. Based on a few experiments it looks like they use 190 DPI as the default resolution. We can extract page images from PDFs to match this using

```
pdftopng -r 190 <pdf filename> <page name prefix>
```

This gives us a way to try and match Plazi markup to the original document.


## Reading

For inspiration on CSS and document layout see [Quick guides to 
making beautiful PDF documents
from HTML and CSS](https://css4.pub/). Much of this is proprietary, but still interesting.

See [Box alignment in grid layout](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_Grid_Layout/Box_Alignment_in_CSS_Grid_Layout) for possible terms to describe paragraph layout.

