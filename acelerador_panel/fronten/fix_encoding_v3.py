
import os

path = r'c:\Users\nacho\OneDrive\Escritorio\Proyecto_Acelerador_despliegue\Proyecto-Acelerador\acelerador_panel\fronten\panel_tutor.php'

def fix_content(content_bytes):
    c = content_bytes
    
    # Mapeo de bytes corruptos (UTF-8 interpretado como Latin-1 y re-guardado)
    repls = [
        # Secuencias de tildes y eñes
        (b'\xc3\x83\xc2\xa1', b'\xc3\xa1'), # á
        (b'\xc3\x83\xc2\xa9', b'\xc3\xa9'), # é
        (b'\xc3\x83\xc2\xad', b'\xc3\xad'), # í
        (b'\xc3\x83\xc2\xb3', b'\xc3\xb3'), # ó
        (b'\xc3\x83\xc2\xba', b'\xc3\xba'), # ú
        (b'\xc3\x83\xc2\xb1', b'\xc3\xb1'), # ñ
        (b'\xc3\x83\xc2\x81', b'\xc3\x81'), # Á
        (b'\xc3\x83\xc2\x89', b'\xc3\x89'), # É
        (b'\xc3\x83\xc2\x8d', b'\xc3\x8d'), # Í
        (b'\xc3\x83\xc2\x93', b'\xc3\x93'), # Ó
        (b'\xc3\x83\xc2\x9a', b'\xc3\x9a'), # Ú
        (b'\xc3\x83\xc2\x91', b'\xc3\x91'), # Ñ
        (b'\xc3\x83\xc2\xbf', b'\xc2\xbf'), # ¿
        (b'\xc3\x83\xc2\xa1', b'\xc2\xa1'), # ¡ (wait, 0xC2 0xA1 is ¡)
        
        # Secuencias de diseño de caja (box drawing)
        (b'\xc3\xa2\xe2\x82\xac\xe2\x80\x9d', b'\xe2\x94\x80'), # â€” -> ─
        (b'\xc3\x83\xe2\x80\x9d', b'\xe2\x94\x80'), # Ã” -> ─
        (b'\xc3\x83\xa2\xe2\x82\xac\xe2\x80\x9d', b'\xe2\x94\x80'),
        (b'\xe2\x94\x80\xe2\x94\x80', b'\xe2\x94\x80\xe2\x94\x80'),
    ]
    
    # Reemplazos específicos de caracteres que fallan habitualmente
    # Ã³ -> ó
    # Ã¡ -> á
    
    for bad, good in repls:
        c = c.replace(bad, good)
        
    # Arreglo de "Ã " que a menudo es "í" seguido de espacio o similar
    c = c.replace(b'\xc3\x83\x20', b'\xc3\xad\x20')
    
    return c

with open(path, 'rb') as f:
    content = f.read()

fixed = fix_content(content)

with open(path, 'wb') as f:
    f.write(fixed)

print("Reparacion de codificacion v3 finalizada.")
