<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir PDF</title>
</head>
<body>

    <h1>Subida de PDF</h1>

    <form action="subir.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="pdf" accept="application/pdf" required>
        <button type="submit">Subir PDF</button> <a href="/subir.php"></a>
    </form>

</body>
</html>