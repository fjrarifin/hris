<!DOCTYPE html>
<html>

<head>
	<title>RFID Scanner</title>
</head>

<body>

	<h1>RFID TEST</h1>

	<h2>UID:</h2>
	<h1 id="uid">Waiting Scan...</h1>

	<script>
		async function checkRFID() {

			let res = await fetch('/api/rfid');
			let data = await res.json();

			if (data.uid) {
				document.getElementById("uid").innerText = data.uid;
			}

		}

		setInterval(checkRFID, 500);
	</script>

</body>

</html>
