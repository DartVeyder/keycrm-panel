<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Excel File</title>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <div class="card">
        <div class="card-header">
            <h3>Upload Excel File</h3>
        </div>
        <div class="card-body">
            <form action="upload.php" method="post" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="file">Choose Excel File</label>
                    <input type="file" class="form-control-file" id="file" name="file" accept=".xlsx" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
            <span>Скачати: </span>  <a href="xlsx/discounts.xlsx?<?php echo time(); ?>" >discounts.xlsx</a> <br>
            <span>Скачати: </span>  <a href="example.xlsx">Шаблон</a> <br>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
