var ActiveDocument = function() {
    return {
        arrayCompare: function(a1, a2) {
            if (a1.length != a2.length) return false;
            var length = a2.length;
            for (var i = 0; i < length; i++) {
                if (a1[i] !== a2[i]) return false;
            }
            return true;
        },
        inArray: function(needle, haystack) {
            var length = haystack.length;
            for(var i = 0; i < length; i++) {
                if(typeof haystack[i] == 'object') {
                    if(ActiveDocument.arrayCompare(haystack[i], needle)) return true;
                } else {
                    if(haystack[i] == needle) return true;
                }
            }
            return false;
        }
    };
}();