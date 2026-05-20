
import os

path = r'c:\Users\nacho\OneDrive\Escritorio\Proyecto_Acelerador_despliegue\Proyecto-Acelerador\acelerador_panel\fronten\panel_tutor.php'

with open(path, 'rb') as f:
    content = f.read()

# Reemplazos de secuencias corruptas (UTF-8 interpretado como Latin-1)
replacements = {
    b'\xc3\xa1': b'\xc3\xa1', # á (if it's already correct, this won't change it)
    b'\xc3\xa9': b'\xc3\xa9', # é
    b'\xc3\xad': b'\xc3\xad', # í
    b'\xc3\xb3': b'\xc3\xb3', # ó
    b'\xc3\xba': b'\xc3\xba', # ú
    b'\xc3\xb1': b'\xc3\xb1', # ñ
    b'\xc3\x81': b'\xc3\x81', # Á
    b'\xc3\x89': b'\xc3\x89', # É
    b'\xc3\x8d': b'\xc3\x8d', # Í
    b'\xc3\x93': b'\xc3\x93', # Ó
    b'\xc3\x9a': b'\xc3\x9a', # Ú
    b'\xc3\x91': b'\xc3\x91', # Ñ
    b'\xc2\xbf': b'\xc2\xbf', # ¿
    b'\xc2\xa1': b'\xc2\xa1', # ¡
}

# Pero el problema es que el archivo TIENE las secuencias corruptas.
# Si el archivo tiene Ã¡ (0xC3 0x83 0xC2 0xA1), queremos á (0xC3 0xA1).

corrupted_replacements = {
    b'\xc3\x83\xc2\xa1': b'\xc3\xa1', # á
    b'\xc3\x83\xc2\xa9': b'\xc3\xa9', # é
    b'\xc3\x83\xc2\xad': b'\xc3\xad', # í
    b'\xc3\x83\xc2\xb3': b'\xc3\xb3', # ó
    b'\xc3\x83\xc2\xba': b'\xc3\xba', # ú
    b'\xc3\x83\xc2\xb1': b'\xc3\xb1', # ñ
    b'\xc3\x82\xc2\xbf': b'\xc2\xbf', # ¿
    b'\xc3\x82\xc2\xa1': b'\xc2\xa1', # ¡
    b'\xc3\x83\xc2\x81': b'\xc3\x81', # Á
    b'\xc3\x83\xc2\x89': b'\xc3\x89', # É
    b'\xc3\x83\xc2\x8d': b'\xc3\x8d', # Í
    b'\xc3\x83\xc2\x93': b'\xc3\x93', # Ó
    b'\xc3\x83\xc2\x9a': b'\xc3\x9a', # Ú
    b'\xc3\x83\xc2\x91': b'\xc3\x91', # Ñ
}

for bad, good in corrupted_replacements.items():
    content = content.replace(bad, good)

# Fix for "Ã " which often appears for "í" or similar
content = content.replace(b'\xc3\x83\x20', b'\xc3\xad') # í

with open(path, 'wb') as f:
    f.write(content)

print("Reparación completada.")
