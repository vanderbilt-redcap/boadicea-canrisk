(function () {

    function runBoadiceaPush() {
        $.ajax({
			url: BOADICEA_PUSH_AJAX_URL,
			type: "GET"
		})
		.done(function(json) {
			if(json.result) {
                alert('Boadicea push completed');
                location.reload();
            } else {
                console.log('Something went wrong');
            }
		});
    }

    const dataEntryTopOptions =document.getElementById("dataEntryTopOptions");
    let topButton = document.createElement("button");
    topButton.innerHTML = "Run Boadicea Push";
    topButton.className = "jqbuttonmed ui-button ui-corner-all ui-widget";
    topButton.addEventListener("click",runBoadiceaPush);
    dataEntryTopOptions.appendChild(topButton);

})();