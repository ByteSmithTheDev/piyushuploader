<!DOCTYPE html>
<html>
<head>
    <title>File Upload</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; }
        .upload-container { border: 2px dashed #ccc; padding: 20px; text-align: center; }
        .progress { margin-top: 10px; display: none; }
    </style>
</head>
<body>
    <h1>Upload File</h1>
    
    <div class="upload-container">
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="file" name="file" id="fileInput" required>
            <button type="submit">Upload</button>
        </form>
        <div class="progress" id="progressBar">
            <progress value="0" max="100"></progress>
            <span id="status">Uploading...</span>
        </div>
        <div id="result"></div>
    </div>

    <script>
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const fileInput = document.getElementById('fileInput');
            const progressBar = document.getElementById('progressBar');
            const resultDiv = document.getElementById('result');
            
            if (fileInput.files.length === 0) {
                resultDiv.innerHTML = '<p style="color:red">Please select a file</p>';
                return;
            }
            
            const formData = new FormData();
            formData.append('file', fileInput.files[0]);
            
            progressBar.style.display = 'block';
            
            fetch('upload.php', {
                method: 'POST',
                body: formData,
                credentials: 'include' // Important for session cookies
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `
                        <p style="color:green">File uploaded successfully!</p>
                        <p>URL: <a href="${data.url}" target="_blank">${data.url}</a></p>
                    `;
                } else {
                    resultDiv.innerHTML = `<p style="color:red">Error: ${data.error}</p>`;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<p style="color:red">Upload failed: ${error}</p>`;
            })
            .finally(() => {
                progressBar.style.display = 'none';
            });
        });
    </script>
</body>
</html>