var isIE = (navigator.userAgent && navigator.userAgent.toLowerCase().indexOf("msie") > -1);
var isIE8 = (isIE && navigator.userAgent.toLowerCase().indexOf("msie 8") > -1);
var isIE7 = (isIE && navigator.userAgent.toLowerCase().indexOf("msie 7") > -1);
var isIE6 = isIE && !isIE7 && !isIE8;

var extraHeight = 0;                                                                                                                                                                                                                         
                                                                                                                                                                                                                                             
if (isIE8) {                                                                                                                                                                                                                                 
        extraHeight = 40;                                                                                                                                                                                                                    
} else if (!document.all) {                                                                                                                                                                                                                  
        extraHeight = 16;                                                                                                                                                                                                                    
}                                                                                                                                                                                                                                            

function resizeCaller() {
        resizeLPFrame(); 
        var f = document.getElementById("lpframe");
        if (f.addEventListener) {                  
                f.addEventListener("load", resizeLPFrame, false);
        } else if (f.attachEvent){                               
                f.detachEvent("onload", resizeLPFrame);          
                f.attachEvent("onload", resizeLPFrame);          
        }                                                        
}                                                                

(function() {
	if (typeof allowResize !== "undefined" && allowResize !== true) {
		return;		
	}
    var old_onload = window.onload;
    window.onload = function() {
            var frame_old_onreadystatechange = 0;
            old_onload();
            try {
                    var f = document.getElementById("lpframe");

                    if (f && typeof(f.Document) == "object") { // IE
                            if(frame_old_onreadystatechange === 0) {
                                    frame_old_onreadystatechange = f.onreadystatechange;
                                    f.onreadystatechange = function() {
                                            if (typeof (frame_old_onreadystatechange) == "function")
                                                    frame_old_onreadystatechange();
                                            if (f.readyState == "complete"){
                                                    resizeLPFrame();
					    }
                                            return(true);
                                    }
					resizeLPFrame();
                            }
                    } else {
                        f.setAttribute("onload", "resizeLPFrame()");
                    }
		    
            } catch (e) {
            }
    }
}) ();

function resizeLPFrame() {
	var f = document.getElementById("lpframe");
        if (f){
                var placeholder = document.getElementById('placeholder');

                if(placeholder && typeof(baseWidth)=='number' && placeholder.width>baseWidth){
                      placeholder.width=baseWidth;
                }

                var doc;
                f.style.display = "block";
                if (f.contentDocument) { // firefox
                                resizeOnly();
                                doc = f.contentDocument;
                        doc.body.addEventListener("DOMNodeInserted", resizeOnly, false);
                } else {
                        try { // access migth be denied
                                if (typeof(f.Document) == "object") { // IE
                                        resizeOnly();
                                        doc = f.Document;
                                        doc.body.onresize = resizeOnly;
                                }
                        } catch (e) {
                        }
                }
                if (doc) {
                        var welcome_img = doc.getElementById("welcome_image");
                        if (welcome_img)
                                welcome_img.onload = resizeOnly;
                }
        }
}

function resizeOnly() {
        var f = document.getElementById("lpframe");
        if (f) {
                var doc;
                f.style.display = "block";

                doc = f.contentDocument /* FireFox */|| f.Document /* IE */|| null;

                if(doc && doc.body && doc.body.offsetHeight) {
                        if(f.height !== doc.body.scrollHeight + extraHeight){
                	        f.height = doc.body.scrollHeight + extraHeight;
				if(isIE6){
					var body = document.getElementById('body')
					if(body)
						body.style.position = body.style.position || 'relative';
				}
			}
                }

                if(doc && doc.body && doc.body.parentNode && typeof(baseWidth)=='number'){
                        var htmlTag = doc.body.parentNode
                        var placeholder = document.getElementById('placeholder');

                        if(htmlTag.scrollWidth > placeholder.width)
                                placeholder.width = htmlTag.scrollWidth;
                }
        }
        return (true);
}


function onResizeMessage(width, height) {
    if (!height || !width) {
	    	if (console && console.error) {
				console.error("Ignoring onResizeMessage(): no width or height passed");
			}
	    	
            return;
    }

    var f = document.getElementById("lpframe");

    f.height = height + extraHeight;
    f.width = width;
}

