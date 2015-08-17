(function() {
    document.getElementById("chan").onchange = function() {
        if (this.selectedIndex!==0) {
            window.location.href = this.value;
        }
    };
})();
