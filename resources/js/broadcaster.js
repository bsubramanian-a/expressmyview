import AgoraRTC from "agora-rtc-sdk-ng";
import AgoraRTM from 'agora-rtm-sdk';
import { createStore } from 'redux';
import host from './host';
import devices from './devices';
import deviceInfo from './deviceInfo';

// var AGORA_APP_ID = '{{ env('AGORA_APP_ID') }}';
// const AGORA_APP_ID = process.env.AGORA_APP_ID;

$(function(){
  console.log(
    'agora sdk version: ' +
      AgoraRTC.VERSION +
      ' compatible: ' +
      AgoraRTC.checkSystemRequirements()
  );

  let camList;
  let micList;
  let playbackList;

  let volumeLevelTimer = null;
  let abruptClose = null;

  const encoderConfig = {
    width: {max: 1280, min: 640},
    height: {max: 720, min: 480}
  };

  const hostvideoDiv = $('#host-video');
  hostvideoDiv.hide();
  const hostscreenDiv = $('#host-screen');
  hostscreenDiv.hide();
  const spinnerDiv = $('#spinner');
  spinnerDiv.hide();
  const posterimage = $('#posterimage');
  posterimage.height(480).hide()

  $('#statusScreen').hide();

  const hostStore = createStore(host);
  hostStore.subscribe(() => {
    const hostState = hostStore.getState();
    console.log(hostState);
    if (hostState.connectionState === 'CONNECTED' || hostState.connectionState === 'DISCONNECTED') {
      spinnerDiv.hide();
      posterimage.hide();
    }
    if (hostState.webcamOff && (hostState.connectionState === 'CONNECTING' || hostState.connectionState === 'RECONNECTING')) {
      posterimage.show();
      posterimage.css({'background-color': '#D6EAF8'});
      spinnerDiv.show();
    }
    if (hostState.connectionState === 'DISCONNECTED' && (hostState.webcamOff || !hostState.localVideoTrackavailable)) {
      $('#statusScreen').show();
      posterimage.show();
      posterimage.css({'background-color': '#E59866'});
      $('#statusScreen span').html('STATUS: ' + hostState.connectionState + '. WEBCAM IS OFF.');
    }
    if (hostState.localVideoTrackavailable && hostState.localScreenTrackavailable) {
      // const vheight = $('#host-screen video').height();
      // $('#video-section').height(vheight+5);
      hostscreenDiv.css({position: 'absolute'});
      hostvideoDiv.addClass("col-md-3");
      hostvideoDiv.css({position: 'absolute', 'z-index': 500, width: '100%', height: 'auto'});
    }
    if (hostState.localVideoTrackavailable) {
      hostvideoDiv.show();
    }
    if (hostState.localScreenTrackavailable) {
      hostscreenDiv.show();
    }
    if ((hostState.localScreenTrackavailable && !hostState.localVideoTrackavailable)
      || (!hostState.localScreenTrackavailable && hostState.localVideoTrackavailable)) {
      console.log('reset the css here');
      hostvideoDiv.css({position: 'relative'});
      hostvideoDiv.removeClass("col-md-3");
      hostscreenDiv.css({position: 'relative', 'z-index': 'auto'});
    }
    if (!hostState.localVideoTrackavailable && !hostState.localScreenTrackavailable) {
      console.log(hostState);
      posterimage.show();
      posterimage.css({'background-color': '#EBEDEF'});
      spinnerDiv.show();
      hostvideoDiv.hide();
      hostvideoDiv.css({position: 'relative'});
      hostvideoDiv.removeClass("col-md-3");
      hostscreenDiv.css({position: 'relative', 'z-index': 'auto'});
      console.log('hostscreendiv hidden here1');
      hostscreenDiv.hide();
      hostscreenDiv.empty();
    }
    if (hostState.connectionState === 'DISCONNECTED') {
      $('#connectionState').html(hostState.connectionState + ', click on <i class="fas fa-podcast"></i>'+' to go live!');
    }
    if (hostState.connectionState === 'CONNECTING') {
      $('#connectionState').html(hostState.connectionState);
    }
    if (hostState.connectionState === 'RECONNECTING') {
      $('#connectionState').html('DISCONNECTED!! '+hostState.connectionState + '...');
    }
    if (hostState.webcamOff && !hostState.localScreenTrackavailable) {
      $('#statusScreen').show();
      posterimage.show();
      posterimage.css({'background-color': '#E59866'});
      $('#statusScreen span').html('STATUS: ' + hostState.connectionState + '. WEBCAM IS OFF');
      hostvideoDiv.hide();
      hostvideoDiv.css({position: 'relative'});
      hostvideoDiv.removeClass("col-md-3");
      hostscreenDiv.css({position: 'relative', 'z-index': 'auto'});
      console.log('hostscreendiv hidden here2');
      hostscreenDiv.hide();
      hostscreenDiv.empty();
    }
    if (!hostState.webcamOff && (hostState.connectionState === 'CONNECTED' || hostState.connectionState === 'DISCONNECTED')) {
      $('#statusScreen').hide();
      posterimage.hide();
      posterimage.css({'background-color': '#E59866'});
      $('#statusScreen span').html('WEBCAM IS ON');
      // hostvideoDiv.hide();
    }
    if (hostState.connectionState === 'CONNECTED') {
      $('#connectionState').html(hostState.connectionState + ', click on <i class="fas fa-phone-slash"></i>'+' to offline!');
    }
    if (!hostState.micMuted && !hostState.webcamOff && hostState.localAudioTrackavailable && (hostState.localVideoTrackavailable || hostState.localScreenTrackavailable)) {
      $('#golive-btn').prop('disabled', false);
    } else {
      $('#golive-btn').prop('disabled', true);
    }
  });
  const devicesStore = createStore(devices);
  devicesStore.subscribe(() => {
    const devicesState = devicesStore.getState();
    if (devicesState.camList !== camList) {
      camList = devicesState.camList;
      const camdd = $('#camera-list-select');
      camdd.val('');
      $.each(devicesState.camList, (indx,cam) => {
        camdd.append($('<option></option>').val(cam.deviceId).html(cam.label));
      })
      camdd.append($('<option>').val('screen').html('Share screen'));
      camdd.append($('<option>').val('screenandvideo').html('Share screen + webcam'));
    }
    if (devicesState.micList !== micList) {
      micList = devicesState.micList;
      const micdd = $('#mic-list-select');
      micdd.val('');
      $.each(devicesState.micList, (indx,mic) => {
        micdd.append($('<option></option>').val(mic.deviceId).html(mic.label));
      })
    }
  });

  // const viewersStore = createStore(connectedViewers);
  // viewersStore.subscribe(() => {
  //   const viewersState = viewersStore.getState();
  //   $('#liveviewerscount').html('<i class="fas fa-eye"></i> '+viewersState.viewersCount);
  // });

  $('#liveviewerscount').html('<i class="fas fa-eye"></i> '+'xx');

  // https://docs.agora.io/en/Agora%20Platform/token?platform=All%20Platforms
  // https://docs.agora.io/en/Agora%20Platform/token_server

  var token = servertoken;
  var tokenrtm = servertokenrtm;

  var bclient = {
    // For the local client.
    client: null,
    screenclient: null,
    // For the local audio and video tracks.
    localAudioTrack: null,
    localVideoTrack: null,
    localScreenTrack: null,
    localAddScreenTrack: null,
  };

  var options = {
    appId: AGORA_APP_ID,
    channel: channelname,
    token: token,
    role: 'host',
  }

  // Level: 1: INFO, 0: DEBUG, 4: NONE, 2: WARNING, 3: ERROR
  if (APP_DEBUG) {
    window.AgoraRTC = AgoraRTC;
    window.bclient = bclient;
    AgoraRTC.setLogLevel(2);
  } else {
    AgoraRTC.setLogLevel(2);
  }

  AgoraRTC.getCameras().then((list) => {
    devicesStore.dispatch({type: 'SET_DEVICE_LIST', payload: {devicelist: list, devicetype: 'CAM'}});
  });
  AgoraRTC.getMicrophones().then((list) => {
    devicesStore.dispatch({type: 'SET_DEVICE_LIST', payload: {devicelist: list, devicetype: 'MIC'}});
  });
  AgoraRTC.getPlaybackDevices().then((list) => {
    devicesStore.dispatch({type: 'SET_DEVICE_LIST', payload: {devicelist: list, devicetype: 'PBD'}});
  });

  AgoraRTC.onCameraChanged = (info) => {
    if (info.state === 'ACTIVE') {
      devicesStore.dispatch({type: 'ADD_DEVICE', payload: {device: info.device, devicetype: 'CAM'}});
    } else if (info.state === 'INACTIVE') {
      devicesStore.dispatch({type: 'REMOVE_DEVICE', payload: {deviceId: info.device.deviceId, devicetype: 'CAM'}});
    }
  }

  AgoraRTC.onMicrophoneChanged = (info) => {
    if (info.state === 'ACTIVE') {
      devicesStore.dispatch({type: 'ADD_DEVICE', payload: {device: info.device, devicetype: 'MIC'}});
    } else if (info.state === 'INACTIVE') {
      devicesStore.dispatch({type: 'REMOVE_DEVICE', payload: {deviceId: info.device.deviceId, devicetype: 'MIC'}});
    }
  }

  AgoraRTC.onPlaybackDeviceChanged = (info) => {
    if (info.state === 'ACTIVE') {
      devicesStore.dispatch({type: 'ADD_DEVICE', payload: {device: info.device, devicetype: 'PBD'}});
    } else if (info.state === 'INACTIVE') {
      devicesStore.dispatch({type: 'REMOVE_DEVICE', payload: {deviceId: info.device.deviceId, devicetype: 'PBD'}});
    }
  }

  async function startScreenCall() {
    bclient.screenclient = AgoraRTC.createClient({ mode: 'live', codec: 'vp8'});
    bclient.screenclient.setClientRole(options.role);
    const screenuid = await bclient.screenclient.join(options.appId, options.channel, options.token, null);
    const localAddScreenTrack = await AgoraRTC.createScreenVideoTrack({
      encoderConfig: '1080p_1',
      screenSourceType: deviceInfo.flag === 'firefox' ? 'screen' : null }
      ,'auto');
    bclient.localAddScreenTrack = localAddScreenTrack;
    console.log('playing screen track in next line');
    const screenDiv = document.getElementById('host-screen');
    bclient.localAddScreenTrack.play(screenDiv);
    // $('#host-screen').show();
    bclient.localAddScreenTrack.on('track-ended', () => {
      console.log('screen track ended');
      const deviceId = $('#camera-list-select').val();
      $('#camera-list-select').prop("selectedIndex", 0);
      bclient.screenclient.leave();
      bclient.screenclient = null;
      bclient.localAddScreenTrack = null;
      if (deviceId === 'screenandvideo') {

      }
    });
  }

  async function addCameraCall(deviceId) {
    const vtrack = await AgoraRTC.createCameraVideoTrack({cameraId: deviceId, encoderConfig})
    bclient.localVideoTrack = vtrack;
    const hostvideoDiv = document.getElementById('host-video');
    bclient.localVideoTrack.play(hostvideoDiv);
    // $('#host-video').show();
  }

  const RTM = {
    rtmclient: null,
    loggedIn: false,
  }
  let rtmchannel;

  async function JoinChat() {
    RTM.rtmclient = AgoraRTM.createInstance(AGORA_APP_ID);

    // Level: 1: INFO, 0: DEBUG, 4: NONE, 2: WARNING, 3: ERROR
    if (APP_DEBUG) {
      RTM.rtmclient.setParameters({logFilter: AgoraRTM.LOG_FILTER_WARNING}); // AgoraRTM.LOG_FILTER_INFO
    } else {
      RTM.rtmclient.logFilter(AgoraRTM.LOG_FILTER_WARNING);
    }

    RTM.rtmclient.on('ConnectionStateChanged', (newState, reason) => {
      console.log('on connection state changed to ' + newState + ' reason: ' + reason);
    });

    RTM.rtmclient.login({token: tokenrtm, uid: userrtm})
    .then(() => {
      RTM.loggedIn = true;
      rtmchannel = RTM.rtmclient.createChannel(channelname);

      rtmchannel.on('ChannelMessage', ({text}, senderId) => {
        const { msg, displayname, profilepic }  = JSON.parse(text);
        const divID = '_' + Math.random().toString(36).substr(2, 9);
        const chatDiv = $('<div>', {id: divID, class: 'media media-chat'});
        const imgDiv = $('<img>', {class: 'avatar avatar-xs', src: profilepic});
        chatDiv.append(imgDiv);
        const chatBody = $('<div>', {class: 'media-body'});
        const nameBody = $('<p>', {text: 'From: '+displayname});
        const textBody = $('<p>', {html: msg});
        chatBody.append(nameBody);
        chatBody.append(textBody);
        chatDiv.append(chatBody);
        chatDiv.attr('class', 'media media-chat');
        $('#chat-content').append(chatDiv);
      });

      rtmchannel.join()
      .then(() => {
        console.log('chat channel joining success');
      }).catch((error) => {
        console.log('Chat channel joining error: ', error);
      });

    })
    .catch((error) => {
      console.log('RTM login error: ' + error);
    });
  };

  function sendChatMessage(textmsg, emoji=false) {
    const { msg, displayname } = JSON.parse(textmsg);

    if (rtmchannel) {
      rtmchannel.sendMessage({text: textmsg})
      .then(() => {
        const divID = '_' + Math.random().toString(36).substr(2, 9);
        const chatDiv = $('<div>', {id: divID, class: 'media media-chat media-chat-reverse'});
        const chatBody = $('<div>', {class: 'media-body'});
        const nameBody = $('<p>', {text: 'From: '+displayname});
        // let textBody;
        // if (emoji) {
        //   textBody = JSON.parse(msg);
        // } else {
        //   textBody = $('<p>', {text: msg});
        // }
        const textBody = $('<p>', {html: msg});
        // chatBody.append(nameBody);
        chatBody.append(textBody);
        chatDiv.append(chatBody);
        $('#chat-content').append(chatDiv);
        if (!emoji)  $('#publisher-input').val('');
      })
      .catch((error) => { console.log(error)});
    }
  };

  $('#publisher-input').keyup((e) => {
    if (e.keyCode == 13) {
      const msg = $('#publisher-input').val();
      if (msg.length < 1) return;
      const textmsg = JSON.stringify({msg,displayname,profilepic,emoji: false});
      sendChatMessage(textmsg);
    }
  });

  $('#publisher-btn').click((e) => {
    const msg = $('#publisher-input').val();
    if (msg.length < 1) return;
    const textmsg = JSON.stringify({msg,displayname,profilepic,emoji: false});
    sendChatMessage(textmsg);
  });

  $('#camera-list-select').change(() => {
    const deviceId = $('#camera-list-select').val();
    if (deviceId === 'screen') {
      if (bclient.localAddScreenTrack !== null) {
        console.log('removing localAddScreenTrack');
        if (bclient.screenclient === null) {
          console.log('bclient.screenclient === null');
          bclient.localAddScreenTrack.close();
        } else {
          bclient.screenclient.unpublish(bclient.localAddScreenTrack)
          .then(() => {
            console.log('bclient.screenclient.unpublish');
            bclient.localAddScreenTrack.close();
            bclient.screenclient.leave();
            bclient.screenclient = null;
            bclient.localAddScreenTrack = null;
          })
          .catch((error) => {
            console.log(error);
            return;
          });
        }
      }
      if (bclient.localVideoTrack !== null) {
        if (bclient.client === null) {
          $('#camera-list-select').prop("selectedIndex", 0);
          console.log('start broadcasting first to share the screen');
        } else {
          bclient.client.unpublish(bclient.localVideoTrack)
          .then(() => {
            bclient.localVideoTrack.close();
            bclient.localVideoTrack = null;
            hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: false }});
            hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: true}});
            AgoraRTC.createScreenVideoTrack({
              encoderConfig: '1080p_1',
              screenSourceType: deviceInfo.flag === 'firefox' ? 'screen' : null }
              ,'auto')
              .then((localScreenTrack) => {
                bclient.localScreenTrack = localScreenTrack;
                if (bclient.client === null) {
                  const screenElem = document.getElementById('host-screen');
                  localScreenTrack.play(screenElem);
                  hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: true }});
                  $('#camtoggle-btn').prop('disabled', true);
                  hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
                } else {
                  bclient.client.publish(localScreenTrack)
                  .then(() => {
                    const screenElem = document.getElementById('host-screen');
                    localScreenTrack.play(screenElem);
                    hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: true }});
                    $('#camtoggle-btn').prop('disabled', true);
                    hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
                  })
                  .catch((error) => {
                    console.log(error);
                    return;
                  });
                }
                bclient.localScreenTrack.on('track-ended', () => {
                  console.log('screen track ended');
                  bclient.client.unpublish(bclient.localScreenTrack)
                  .then(() => {
                    bclient.localScreenTrack = null;
                    console.log('bclient.client.unpublish(bclient.localScreenTrack)');
                    hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: false }});
                    hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: true}});
                    $('#camera-list-select').prop("selectedIndex", 0);
                    $('#camtoggle-btn').prop('disabled', false);
                  })
                  .catch((err) => {
                    console.log(err);
                    bclient.localScreenTrack = null;
                    hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: false }});
                    hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: true}});
                    $('#camera-list-select').prop("selectedIndex", 0);
                    $('#camtoggle-btn').prop('disabled', false);
                  });

                });
              })
              .catch((error) => {
                console.log(error);
                bclient.localScreenTrack = null;
                hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: false }});
                hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: true}});
                $('#camera-list-select').prop("selectedIndex", 0);
                $('#camtoggle-btn').prop('disabled', false);
                return;
              });
          })
          .catch((error) => {
            console.log(error);
            return;
          });
        }
      }
    } else if (deviceId === 'screenandvideo') {
      if (bclient.client === null) {
        $('#camera-list-select').prop("selectedIndex", 0);
        console.log('start broadcasting first to share the screen');
        return;
      }
      if (bclient.localScreenTrack) {
        console.log('here1');
        if (bclient.client === null) {
          console.log('here2');
          bclient.localScreenTrack.close();
        }
        else {
          console.log('here3');
          bclient.client.unpublish(bclient.localScreenTrack)
          .then(() => {
            console.log('here4');
            bclient.localScreenTrack.close();
            console.log('here5');
          })
          .catch((error) => {
            console.log(error);
            return;
          })
        }
      }
      if (bclient.localAddScreenTrack === null && bclient.localVideoTrack !== null) {
        console.log('here6');
        startScreenCall()
        .then(() => {
          console.log('here7');
          bclient.screenclient.publish(bclient.localAddScreenTrack);
          console.log('here8');
          const hostState = hostStore.getState();
          hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: true }});
          hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
          $('#camtoggle-btn').prop('disabled', true);
          if (hostState.webcamOff) {
            bclient.localVideoTrack.setEnabled(true)
            .then(() => {
              $('#camtoggle-icon.fas').toggleClass('fa-video-slash fa-video');
              $('#camtoggle-icon').css('color','green');
              hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
            });
          }
        })
        .catch((error) => {
          console.log(error);
        });
      }
      if (bclient.localAddScreenTrack === null && bclient.localVideoTrack === null) {
        console.log('here9');
        Promise.all([startScreenCall(), addCameraCall()])
        .then(() => {
          console.log('here10');
          bclient.screenclient.publish(bclient.localAddScreenTrack);
          bclient.client.publish(bclient.localVideoTrack)
          hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: true }});
          hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
          $('#camtoggle-btn').prop('disabled', true);
          hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
        })
        .catch((err) => {console.log(err)});
      }
    }
    else {
      if (bclient.localScreenTrack !== null) {
        if (bclient.client === null) {
          bclient.localScreenTrack.close();
        }
        else {
          bclient.client.unpublish(bclient.localScreenTrack)
          .then(() => {
            bclient.localScreenTrack.close();
            bclient.localScreenTrack = null;
            console.log('switchin to webcam, localScreenTrack unpublished');
            hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: false }});
          })
          .catch((error) => {
            console.log(error);
            return;
          })
        }
      }
      if (bclient.localAddScreenTrack !== null) {
        if (bclient.screenclient === null) {
          bclient.localAddScreenTrack.close();
        } else {
          bclient.screenclient.unpublish(bclient.localAddScreenTrack)
          .then(() => {
            bclient.localAddScreenTrack.close();
            bclient.screenclient.leave();
            bclient.screenclient = null;
            bclient.localAddScreenTrack = null;
            console.log('switchin to webcam, localAddScreenTrack unpublished');
            hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: false }});
          })
          .catch((error) => {console.log(error)});
          return;
        }
      }
      if (bclient.localVideoTrack !== null) {
        bclient.localVideoTrack.setDevice(deviceId);
        $('#camtoggle-btn').prop('disabled', false);
        $('#camtoggle-icon').css('color','green');
      } else {
        AgoraRTC.createCameraVideoTrack({cameraId: deviceId, encoderConfig})
        .then((vtrack) => {
          bclient.localVideoTrack = vtrack;
          const videoElem = document.getElementById('host-video');
          vtrack.play(videoElem);
          hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
          $('#camtoggle-btn').prop('disabled', false);
          $('#camtoggle-icon').css('color','green');
          if (bclient.client !== null) bclient.client.publish(vtrack);
        })
        .catch((error) => {
          console.log(error);
        });
      }
    }
  });

  $('#mic-list-select').change(() => {
    console.log($('#mic-list-select').val(), $('#mic-list-select').text());
    const deviceId = $('#mic-list-select').val();
    if (bclient.localAudioTrack){
      bclient.localAudioTrack.setDevice(deviceId)
    }
  })

  async function startBroadcasting() {
    bclient.client = AgoraRTC.createClient({mode: 'live', codec: 'vp8'});
    bclient.client.setClientRole(options.role);

    bclient.client.on('connection-state-change', (currState, prevState, reason) => {
      console.log('Connection state: '+currState);
      hostStore.dispatch({type: 'SET_CONN_STATE', payload: { connectionState: currState }})
    });

    bclient.client.on('token-privilege-will-expire', async function() {
      await bclient.client.renewToken(options.token)
    });

    bclient.client.on('token-privilege-did-expire', async function() {
      await bclient.client.renewToken(options.token)
    });

    bclient.client.on('network-quality', (quality) => {
      // const { downlinkNetworkQuality, uplinkNetworkQuality } = quality;
      // console.log(quality);
    });

    bclient.client.on('exception', (event) => {
      console.log(event);
    });

    bclient.client.on('user-joined', (user) => {
      console.log('user-joined', user);
      // viewersStore.dispatch({type: 'INCREASE_VIEWERS_COUNT'})
    });

    bclient.client.on('user-left', (user) => {
      console.log('user-left', user);
      // viewersStore.dispatch({type: 'DECREASE_VIEWERS_COUNT'})
    });

    const uid = await bclient.client.join(options.appId, options.channel, options.token, null);
    if (bclient.localAudioTrack === null) {
      const deviceId = $('#mic-list-select').val();
      bclient.localAudioTrack = await AgoraRTC.createMicrophoneAudioTrack({microphoneId: deviceId});
      hostStore.dispatch({type: 'AUDIO_TRACK_AVAILABLE', payload: { localAudioTrackavailable: true }});
      hostStore.dispatch({type: 'SET_MIC_MUTED', payload: {flag: false}});
      if (volumeLevelTimer !== null) {
        clearInterval(volumeLevelTimer);
        volumeLevelTimer = null;
      }
      volumeLevelTimer = setInterval(() => {
        const volLevel = bclient.localAudioTrack.getVolumeLevel();
        $('#volumelevel').val(volLevel);
      }, 1000);
      $('#mictoggle-btn').prop('disabled', false);
      $('#mictoggle-icon').css('color','green');
      $('#mictoggle-icon.fas').attr('class','fas fa-microphone');
    }
    // Save this host uid on MySQL DB
    console.log(uid);
    if (bclient.localVideoTrack === null) {
      const deviceId = $('#camera-list-select').val();
      bclient.localVideoTrack = await AgoraRTC.createCameraVideoTrack({cameraId: deviceId, encoderConfig});
      const videoElem = document.getElementById('host-video');
      bclient.localVideoTrack.play(videoElem);
      hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
      hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
      $('#camtoggle-btn').prop('disabled', false);
      $('#camtoggle-icon').css('color','green');
      $('#camtoggle-icon.fas').attr('class','fas fa-video');
    }

    await bclient.client.publish([bclient.localAudioTrack, bclient.localVideoTrack]);
    $('#golive-btn').prop('disabled', true);
    $('#exit-btn').prop('disabled', false);
    await JoinChat();
  }

  async function leaveCall() {
    // Destroy the local audio and video tracks.

    if (bclient.localAudioTrack !== null) bclient.localAudioTrack.close();
    if (bclient.localVideoTrack !== null) bclient.localVideoTrack.close();
    if (bclient.localScreenTrack !== null) bclient.localScreenTrack.close();
    if (bclient.localAddScreenTrack !== null) bclient.localAddScreenTrack.close();

    if (volumeLevelTimer !== null) {
      clearInterval(volumeLevelTimer);
      volumeLevelTimer = null;
    }

    bclient.localAudioTrack = null;
    bclient.localVideoTrack = null;
    // bclient.localScreenTrack = null;
    hostStore.dispatch({type: 'AUDIO_TRACK_AVAILABLE', payload: { localAudioTrackavailable: false }});
    hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: false }});
    hostStore.dispatch({type: 'SCREEN_TRACK_AVAILABLE', payload: { localScreenTrackavailable: false }});
    hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: true}});
    hostStore.dispatch({type: 'SET_MIC_MUTED', payload: {flag: true}});

    // Leave the channel.
    await bclient.client.leave();
    if (bclient.screenclient !== null) bclient.screenclient.leave();
    rtmchannel.leave();
    RTM.rtmclient.logout();
    bclient.client = null;
    $('#camera-list-select').prop("selectedIndex", 0);
    $('#exit-btn').prop('disabled', true);
    $('#golive-btn').prop('disabled', false);
  }

  const deviceIdmic = $('#mic-list-select').val();
  AgoraRTC.createMicrophoneAudioTrack({microphoneId: deviceIdmic}).then((atrack) => {
    bclient.localAudioTrack = atrack;
    hostStore.dispatch({type: 'AUDIO_TRACK_AVAILABLE', payload: { localAudioTrackavailable: true }});
    if (volumeLevelTimer !== null) {
      clearInterval(volumeLevelTimer);
      volumeLevelTimer = null;
    };
    volumeLevelTimer = setInterval(() => {
      const volLevel = bclient.localAudioTrack.getVolumeLevel();
      $('#volumelevel').val(volLevel);
      // console.log('Volume Level: ' + volLevel);
    }, 200);
    $('#mictoggle-btn').prop('disabled', false);
    $('#mictoggle-icon').css('color','green');
  })
  .catch((error) => {
    console.log(error);
  });
  const deviceIdcam = $('#camera-list-select').val();
  AgoraRTC.createCameraVideoTrack({cameraId: deviceIdcam, encoderConfig}).then((vtrack) => {
    bclient.localVideoTrack = vtrack;
    const videoElem = document.getElementById('host-video');
    vtrack.play(videoElem);
    hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
    $('#camtoggle-btn').prop('disabled', false);
    $('#camtoggle-icon').css('color','green');
  })
  .catch((error) => {
    console.log(error);
  });

  $('#mictoggle-btn').prop('disabled', true);
  $('#mictoggle-btn').on('click', () => {
    if (bclient.localAudioTrack) {
      const hostState = hostStore.getState();
      if (hostState.micMuted) {
        bclient.localAudioTrack.setEnabled(true)
        .then(() => {
          $('#mictoggle-icon.fas').toggleClass('fa-microphone-slash fa-microphone');
          $('#mictoggle-icon').css('color','green');
          hostStore.dispatch({type: 'SET_MIC_MUTED', payload: {flag: false}});
        })
        .catch((e) => {
          console.log(e);
        })
      } else {
        bclient.localAudioTrack.setEnabled(false)
        .then(() => {
          $('#mictoggle-icon.fas').toggleClass('fa-microphone-slash fa-microphone');
          $('#mictoggle-icon').css('color','red');
          hostStore.dispatch({type: 'SET_MIC_MUTED', payload: {flag: true}});
        })
        .catch((e) => {
          console.log(e);
        })
      }
    }
  });

  $('#camtoggle-btn').prop('disabled', true);
  $('#camtoggle-btn').on('click', () => {
    console.log('1');
    if (bclient.localScreenTrack !== null) return;
    console.log('2');
    if (bclient.localVideoTrack === null) {
      const deviceIdcam = $('#camera-list-select').val();
      console.log(deviceIdcam);
      AgoraRTC.createCameraVideoTrack({cameraId: deviceIdcam, encoderConfig}).then((vtrack) => {
        bclient.localVideoTrack = vtrack;
        const videoElem = document.getElementById('host-video');
        vtrack.play(videoElem);
        hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
        hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
        $('#camtoggle-btn').prop('disabled', false);
        $('#camtoggle-icon').css('color','green');
        if (bclient.client !== null) bclient.client.publish(vtrack);
      })
      .catch((error) => {
        console.log(error);
        return;
      });
    }
    if (bclient.localVideoTrack !== null) {
      console.log('3');
      const hostState = hostStore.getState();
      if (hostState.webcamOff) {
        bclient.localVideoTrack.setEnabled(true)
        .then(() => {
          $('#camtoggle-icon.fas').toggleClass('fa-video-slash fa-video');
          $('#camtoggle-icon').css('color','green');
          hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: false}});
        });
      } else {
        bclient.localVideoTrack.setEnabled(false)
        .then(() => {
          $('#camtoggle-icon.fas').toggleClass('fa-video-slash fa-video');
          $('#camtoggle-icon').css('color','red');
          hostStore.dispatch({type: 'SET_WEBCAM_OFF', payload: {flag: true}});
        });
      }
    }
  });

  $('#golive-btn').prop('disabled', true);
  $('#golive-btn').on('click', () => {
    const hostState = hostStore.getState();
    if (hostState.connectionState !== 'DISCONNECTED') return;
    startBroadcasting();
    $.ajax({
      url: APP_URL + '/live-stream/setlive',
      // contentType: "application/json",
      dataType: 'json',
      headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
      method: 'post',
      data: { streamid, livestatus: true },
      success: (data) => {
        if (data.status === 1) {
          console.log('Stream set to live!');
        } else {
          console.log(data);
        }
      },
      error: (error) => {
        console.log(error)
      },
      complete: () => {},
    });
  });

  $('#exit-btn').prop('disabled', true);
  $('#exit-btn').on('click', () => {
    leaveCall();
    $.ajax({
      url: APP_URL + '/live-stream/setlive',
      // contentType: "application/json",
      dataType: 'json',
      headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
      method: 'post',
      data: { streamid, livestatus: false},
      success: (data) => {
        if (data.status === 1) {
          console.log('Stream set to not live!');
        } else {
          console.log(data);
        }
      },
      error: (error) => {
        console.log(error)
      },
      complete: () => {},
    });
  });

  $('.dropdown-menu.emoji-item a').on('click', (e) => {
    e.preventDefault();
    const msg = e.target;
    const msgt = $(msg).parent().html();
    const textmsg = JSON.stringify({msg: msgt,displayname,profilepic, emoji: true});
    sendChatMessage(textmsg, true);
  });

  window.addEventListener('beforeunload', abruptClose = (event) => {
    if (volumeLevelTimer !== null) {
      clearInterval(volumeLevelTimer);
      volumeLevelTimer = null;
    };
  });

  // const player = fluidPlayer('fluidplayerdiv');

});
