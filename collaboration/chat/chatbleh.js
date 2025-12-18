$(document).ready(function(){
 $("#submitmsg").click(function(){   
    var clientmsg = $("#usermsg").val();
    $.post("post.php", {text: clientmsg});              
    $("#usermsg").attr("value", "");
    return false;
});
setInterval (loadLog, 1000);
function loadLog(){     
    var oldscrollHeight = $("#chatbox").attr("scrollHeight") - 20;
    $.ajax({
        url: "log.html",
        cache: false,
        success: function(html){        
            $("#chatbox").html(html); 
             
                 
            var newscrollHeight = $("#chatbox").attr("scrollHeight") - 20; 
            if(newscrollHeight > oldscrollHeight){
                $("#chatbox").animate({ scrollTop: newscrollHeight }, 'normal');
            }               
        },
    });
}
});
