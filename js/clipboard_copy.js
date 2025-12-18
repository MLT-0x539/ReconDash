        function copyToClipboard() {
            const code = document.getElementById('output-code').textContent;
            navigator.clipboard.writeText(code).then(() => {
                alert('Code copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy:', err);
            });
        }
