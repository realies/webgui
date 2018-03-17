var eventURL = "/plugins/dynamix.docker.manager/include/Events.php";

function addDockerContainerContext(container, image, template, started, update, autostart, webui, id, Support, Project) {
  var opts = [{header:container, image:"/plugins/dynamix.docker.manager/images/dynamix.docker.manager.png"}];
  if (started && (webui !== "" && webui != "#")) {
    opts.push({text:'WebUI', icon:'fa-globe', href:webui, target:'_blank'});
    opts.push({divider:true});
  }
  if (!update) {
    opts.push({text:'Update', icon:'fa-arrow-down', action:function(e){e.preventDefault(); execUpContainer(container);}});
    opts.push({divider:true});
  }
  if (started) {
    opts.push({text:'Stop', icon:'fa-stop', action:function(e){e.preventDefault(); eventControl({action:"stop", container:id}, 'loadlist');}});
    opts.push({text:'Restart', icon:'fa-refresh', action:function(e){e.preventDefault(); eventControl({action:"restart", container:id}, 'loadlist');}});
  } else {
    opts.push({text:'Start', icon:'fa-play', action:function(e){e.preventDefault(); eventControl({action:"start", container:id}, 'loadlist');}});
  }
  opts.push({divider:true});
  if (location.pathname.indexOf("/Dashboard") === 0) {
    opts.push({text:'Logs', icon:'fa-navicon', action:function(e){e.preventDefault(); containerLogs(container, id);}});
  }
  if (template) {
    opts.push({text:'Edit', icon:'fa-wrench', action:function(e){e.preventDefault(); editContainer(container, template);}});
  }
  opts.push({divider:true});
  opts.push({text:'Remove', icon:'fa-trash', action:function(e){e.preventDefault(); rmContainer(container, image, id);}});
  if (Support) {
    opts.push({divider:true});
    opts.push({text:'Support', icon:'fa-question', href:Support, target:'_blank'});
  }
  if (Project) {
    opts.push({text:'Project Page', icon:'fa-life-ring', href:Project, target:'_blank'});
  }
  context.attach('#'+id, opts);
}
function addDockerImageContext(image, imageTag) {
  var opts = [{header:'(orphan image)'}];
  opts.push({text:"Remove", icon:"fa-trash", action:function(e){e.preventDefault(); rmImage(image, imageTag);}});
  context.attach('#'+image, opts);
}
function execUpContainer(container) {
  var title = "Updating the container: "+container;
  var address = "/plugins/dynamix.docker.manager/include/CreateDocker.php?updateContainer=true&ct[]="+encodeURIComponent(container);
  popupWithIframe(title, address, true, 'loadlist');
}
function popupWithIframe(title, cmd, reload, func) {
  pauseEvents();
  $("#iframe-popup").html('<iframe id="myIframe" frameborder="0" scrolling="yes" width="100%" height="99%"></iframe>');
  $("#iframe-popup").dialog({
    autoOpen:true,
    title:title,
    draggable:true,
    width:800,
    height:((screen.height / 5) * 4) || 0,
    resizable:true,
    modal:true,
    show:{effect:"fade", duration:250},
    hide:{effect:"fade", duration:250},
    open:function(ev, ui) {
      $("#myIframe").attr("src", cmd);
    },
    close:function(event, ui) {
      if (reload && !$("#myIframe").contents().find("#canvas").length) {
        if (func) setTimeout(func+'()',0); else location = window.location.href;
      } else {
        resumeEvents();
      }
    }
  });
  $(".ui-dialog .ui-dialog-titlebar").addClass("menu");
  $(".ui-dialog .ui-dialog-title").css("text-align", "center").css("width", "100%");
  $(".ui-dialog .ui-dialog-content").css("padding", "12");
}
function addContainer() {
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);
  location = path+"/AddContainer";
}
function editContainer(container, template) {
  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0, x);
  location = path+"/UpdateContainer?xmlTemplate=edit:"+template;
}
function updateContainer(container) {
  var body = "Update container: "+container;
  swal({
    title:"Are you sure?",
    text:body,
    type:"warning",
    showCancelButton:true,
    confirmButtonColor:"#8CD4F5",
    confirmButtonText:"Yes, update it!"
  },function(){
    execUpContainer(container);
  });
}
function rmContainer(container, image, id) {
  var body = "Remove container: "+container+"<br><br><label><input id=\"removeimagechk\" type=\"checkbox\" checked style=\"display:inline; width:unset; height:unset; margin-top:unset; margin-bottom:unset\">also remove image</label>";
  $('input[type=button]').prop('disabled',true);
  swal({
    title:"Are you sure?",
    text:body,
    type:"warning",
    html:true,
    showCancelButton:true,
    confirmButtonColor:"#DD6B55",
    confirmButtonText:"Yes, delete it!",
    showLoaderOnConfirm:true
  },function(){
    $('#'+id).find('i').removeClass().addClass('iconstatus fa fa-trash orange-text');
    if ($("#removeimagechk").prop('checked')) {
      eventControl({action:"remove_all", container:id, image:image},'loadlist');
    } else {
      eventControl({action:"remove_container", container:id},'loadlist');
    }
  });
}
function rmImage(image, imageName) {
  var body = "Remove image: "+$('<textarea />').html(imageName).text();
  $('input[type=button]').prop('disabled',true);
  swal({
    title:"Are you sure?",
    text:body,
    type:"warning",
    showCancelButton:true,
    confirmButtonColor:"#DD6B55",
    confirmButtonText:"Yes, delete it!",
    showLoaderOnConfirm:true
  },function(){
    $('#'+image).find('i').removeClass().addClass('iconstatus fa fa-trash orange-text');
    eventControl({action:"remove_image", image:image},'loadlist');
  });
}
function eventControl(params, spin) {
  if (spin) $('#'+params['container']).find('i').addClass('fa-spin');
  $.post(eventURL, params, function(data) {
    if (data.success === true) {
      if (spin) setTimeout(spin+'()',500); else location=window.location.href;
    } else {
      swal({
        title:"Execution error",
        text:data.success, type:"error"
      },function(){
        if (spin) setTimeout(spin+'()',500); else location=window.location.href;
      });
    }
  },'json');
}
function startAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,ct; ct=docker[i]; i++) if (ct.state=='false') $('#'+ct.id).find('i').addClass('fa-spin');
  $.post('/plugins/dynamix.docker.manager/include/ContainerManager.php',{action:'start'},function(){loadlist();});
}
function stopAll() {
  $('input[type=button]').prop('disabled',true);
  for (var i=0,ct; ct=docker[i]; i++) if (ct.state=='true') $('#'+ct.id).find('i').addClass('fa-spin');
  $.post('/plugins/dynamix.docker.manager/include/ContainerManager.php',{action:'stop'},function(){loadlist();});
}
function checkAll() {
  $('input[type=button]').prop('disabled',true);
  $(".updatecolumn").html("<span style=\"color:#267CA8;white-space:nowrap;\"><i class=\"fa fa-spin fa-refresh\"></i> checking...</span>");
  $.post('/plugins/dynamix.docker.manager/include/DockerUpdate.php',{check:true},function(u){loadlist(u);});
}
function updateAll() {
  $('input[type=button]').prop('disabled',true);
  var list = '';
  for (var i=0,ct; ct=docker[i]; i++) if (ct.update=='false') list += '&ct[]='+ct.name;
  var address = "/plugins/dynamix.docker.manager/include/CreateDocker.php?updateContainer=true"+list;
  popupWithIframe('Updating all Containers', address, true, 'loadlist');
}
function containerLogs(container, id) {
  var height = 600;
  var width = 900;
  var run = eventURL+'?action=log&container='+id+'&title=Log for: '+container;
  var top = (screen.height-height) / 2;
  var left = (screen.width-width) / 2;
  var options = 'resizeable=yes,scrollbars=yes,height='+height+',width='+width+',top='+top+',left='+left;
  window.open(run, 'log', options);
}
