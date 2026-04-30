-- Normalise les anciens pdf_path "/demo/Lettre.pdf" (poses par le seed initial)
-- vers le chemin reel du fichier embarque dans le repo : docs/Lettre.pdf.
UPDATE documents
   SET pdf_path = 'docs/Lettre.pdf'
 WHERE pdf_path IN ('/demo/Lettre.pdf', 'demo/Lettre.pdf');
