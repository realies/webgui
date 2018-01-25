function ajaxVMDispatch(currentstate, params){
  $.post("/plugins/dynamix.vm.manager/VMajax.php", params, function( data ) {
    if (data.error) {
      swal({title:"Execution error",text:data.error,type:"error"});
    } else {
      if (!data.state || currentstate != data.state) {
        location = window.location.href;
      }
    }
  }, "json");
}

function addVMContext(name, uuid, template, state, vncurl, log){
  var opts = [{header: name, image: "/plugins/dynamix.vm.manager/images/dynamix.vm.manager.png"}];

  var path = location.pathname;
  var x = path.indexOf("?");
  if (x!=-1) path = path.substring(0,x);

  if (vncurl !== "") {
    opts.push({text: "VNC Remote", icon: "fa-desktop", action: function(e){ e.preventDefault(); window.open(vncurl, '_blank', 'scrollbars=yes,resizable=yes'); } });
    opts.push({divider: true});
  }

  if (state == "running") {

    opts.push({text: "Stop", icon: "fa-stop", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-stop", uuid: uuid });
    }});

    opts.push({text: "Pause", icon: "fa-pause", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-pause", uuid: uuid });
    }});

    opts.push({text: "Restart", icon: "fa-refresh", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-restart", uuid: uuid });
    }});

    opts.push({text: "Hibernate", icon: "fa-bed", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-pmsuspend", uuid: uuid });
    }});

    opts.push({text: "Force Stop", icon: "fa-bomb", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-destroy", uuid: uuid });
    }});

  } else if (state == "pmsuspended") {

    opts.push({text: "Resume", icon: "fa-play", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-pmwakeup", uuid: uuid });
    }});

    opts.push({text: "Force Stop", icon: "fa-bomb", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-destroy", uuid: uuid });
    }});

  } else if (state == "paused" || state == "unknown") {

    opts.push({text: "Resume", icon: "fa-play", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-resume", uuid: uuid });
    }});

    opts.push({text: "Force Stop", icon: "fa-bomb", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-destroy", uuid: uuid });
    }});

  } else {

    opts.push({text: "Start", icon: "fa-play", action: function(e) {
      e.preventDefault();
      ajaxVMDispatch(state, { action: "domain-start", uuid: uuid });
    }});

  }

  opts.push({divider: true});

  if (log !== "") {
    opts.push({text: "Logs", icon: "fa-navicon", action: function(e){ e.preventDefault(); openWindow('/webGui/scripts/tail_log&arg1=' + log, 'Log for: ' + name, 600, 900); } });
  }

  if (state == "shutoff") {

    opts.push({text: "Edit", icon: "fa-pencil", href: path+'/UpdateVM?uuid='+uuid });
    opts.push({text: "Edit XML", icon: "fa-code", href: path+'/UpdateVM?template=Custom&amp;uuid='+uuid });

    opts.push({divider: true});

    opts.push({text: "Remove VM", icon: "fa-minus", action: function(e) {
      e.preventDefault();
      swal({title:"Are you sure?",text:"Remove definition: "+name,type:"warning",showCancelButton:true},function(){ajaxVMDispatch(state,{action:"domain-undefine",uuid:uuid});});
    }});

    if (template != 'OpenELEC') {
      opts.push({text: "Remove VM + Disks", icon: "fa-trash", action: function(e) {
        e.preventDefault();
        swal({title:"Are you sure?",text:"Completely REMOVE "+name+" disk image and definition",type:"warning",showCancelButton:true},function(){ajaxVMDispatch(state,{action:"domain-delete",uuid:uuid});});
      }});
    }

  } else {

    opts.push({text: "View XML", icon: "fa-code", href: path+'/UpdateVM?template=Custom&amp;uuid='+uuid });

  }

  context.attach('#vm-'+uuid, opts);
}