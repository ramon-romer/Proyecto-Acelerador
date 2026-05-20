
import os

path = r'c:\Users\nacho\OneDrive\Escritorio\Proyecto_Acelerador_despliegue\Proyecto-Acelerador\acelerador_panel\fronten\panel_tutor.php'

# Reemplazos de comentarios y textos con caracteres corruptos
# Ã¡ -> á, Ã© -> é, Ã­ -> í, Ã³ -> ó, Ãº -> ú, Ã± -> ñ
# â”€ -> ─
# âœ… -> ✅

def fix_content(content_bytes):
    # Primero arreglamos las secuencias dobles que a veces crea el editor
    # ÃƒÂ¡ -> á
    c = content_bytes
    
    # Secuencias comunes de corrupción UTF-8 interpretada como Latin-1
    # Y luego re-interpretada.
    
    # Mapeo de bytes corruptos a correctos
    repls = [
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
        
        (b'\xc3\xa1', b'\xc3\xa1'), # Ensure it's already á
        
        # Caracteres de diseño
        (b'\xe2\x80\x94', b'\xe2\x80\x94'), # — (already correct if this is the byte)
        (b'\xc3\xa2\xe2\x82\xac\xe2\x80\x9d', b'\xe2\x94\x80'), # â€” -> ─
        (b'\xc3\xa2\xe2\x80\x9d\xc3\xa2\xe2\x80\x9d', b'\xe2\x94\x80\xe2\x94\x80'), # â€â€ -> ──
        (b'\xc3\x83\xe2\x80\x9d', b'\xe2\x94\x80'),
    ]
    
    # Reemplazo manual de las cadenas que vi en el archivo
    c = c.replace(b'â\x94\x80', b'\xe2\x94\x80') # ─
    c = c.replace(b'â\x9c\x85', b'\xe2\x9c\x85') # ✅
    c = c.replace(b'â\x80\x94', b'\xe2\x80\x94') # —
    
    # Si vemos "Ã³" (0xC3 0xB3) pero el archivo es interpretado como Latin-1
    # a veces se guarda como 0xC3 0x83 0xc2 0xb3
    for bad, good in repls:
        c = c.replace(bad, good)
        
    return c

with open(path, 'rb') as f:
    content = f.read()

fixed = fix_content(content)

with open(path, 'wb') as f:
    f.write(fixed)

print("Reparación de codificación finalizada.")
