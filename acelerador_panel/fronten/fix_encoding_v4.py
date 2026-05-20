
import os

path = r'c:\Users\nacho\OneDrive\Escritorio\Proyecto_Acelerador_despliegue\Proyecto-Acelerador\acelerador_panel\fronten\panel_tutor.php'

def fix_content(content_bytes):
    c = content_bytes
    # Reemplazo de la secuencia corrupta de las líneas de separación
    # â”€ is often 0xE2 0x94 0x80. If double encoded or messed up:
    c = c.replace(b'\xe2\x94\x80', b'-') # Replace box drawing with simple dash to be safe
    c = c.replace(b'\xc3\xa2\xe2\x84\xa2\xe2\x82\xac', b'-')
    # Let's just remove those specific decorative lines if they are broken
    import re
    # Pattern for those long lines of â”€
    c = re.sub(b'// \xe2\x94\x80+', b'// ' + b'-'*20, c)
    c = re.sub(b'// \xc3\xa2\xe2\x80\x9d+', b'// ' + b'-'*20, c)
    
    # Generic fixes for common Spanish characters
    c = c.replace(b'\xc3\x83\xc2\xa1', b'\xc3\xa1') # á
    c = c.replace(b'\xc3\x83\xc2\xa9', b'\xc3\xa9') # é
    c = c.replace(b'\xc3\x83\xc2\xad', b'\xc3\xad') # í
    c = c.replace(b'\xc3\x83\xc2\xb3', b'\xc3\xb3') # ó
    c = c.replace(b'\xc3\x83\xc2\xba', b'\xc3\xba') # ú
    c = c.replace(b'\xc3\x83\xc2\xb1', b'\xc3\xb1') # ñ
    
    return c

with open(path, 'rb') as f:
    content = f.read()

fixed = fix_content(content)

with open(path, 'wb') as f:
    f.write(fixed)

print("Reparacion de codificacion v4 (DASH) finalizada.")
