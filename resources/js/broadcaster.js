import AgoraRTC from "agora-rtc-sdk-ng";
import AgoraRTM from 'agora-rtm-sdk';
import { createStore } from 'redux';
import host from './host';
import devices from './devices';
import connectedViewers from './connectedViewers';

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

  const hostStore = createStore(host);
  hostStore.subscribe(() => {
    const hostState = hostStore.getState();
    $('#connectionState').html(hostState.connectionState);
    if (!hostState.micMuted && !hostState.webcamOff && hostState.localAudioTrackavailable && hostState.localVideoTrackavailable) {
      $('#golive-btn').prop('disabled', false);
    } else {
      $('#golive-btn').prop('disabled', true);
    }
  });
  const devicesStore = createStore(devices);
  devicesStore.subscribe(() => {
    const devicesState = devicesStore.getState();
    console.log(devicesState);
    if (devicesState.camList !== camList) {
      camList = devicesState.camList;
      const camdd = $('#camera-list-select');
      $.each(devicesState.camList, (indx,cam) => {
        camdd.append($('<option></option>').val(cam.deviceId).html(cam.label));
      })
    }
    if (devicesState.micList !== micList) {
      micList = devicesState.micList;
      const micdd = $('#mic-list-select');
      $.each(devicesState.micList, (indx,mic) => {
        micdd.append($('<option></option>').val(mic.deviceId).html(mic.label));
      })
    }
  });

  const viewersStore = createStore(connectedViewers);
  viewersStore.subscribe(() => {
    const viewersState = viewersStore.getState();
    $('#liveviewerscount').html('<i class="fas fa-eye"></i> '+viewersState.viewersCount);
  });

  // https://docs.agora.io/en/Agora%20Platform/token?platform=All%20Platforms
  // https://docs.agora.io/en/Agora%20Platform/token_server

  var token = servertoken;
  var tokenrtm = servertokenrtm;

  var bclient = {
    // For the local client.
    client: null,
    // For the local audio and video tracks.
    localAudioTrack: null,
    localVideoTrack: null,
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
    AgoraRTC.setLogLevel(0);
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

  const RTM = {
    rtmclient: null,
    loggedIn: false,
  }
  let rtmchannel;

  async function JoinChat() {
    RTM.rtmclient = AgoraRTM.createInstance(AGORA_APP_ID);
    RTM.rtmclient.on('ConnectionStateChanged', (newState, reason) => {
      console.log('on connection state changed to ' + newState + ' reason: ' + reason);
    });

    RTM.rtmclient.login({token: tokenrtm, uid: userrtm})
    .then(() => {
      RTM.loggedIn = true;
      console.log('RTM login successfull');
      rtmchannel = RTM.rtmclient.createChannel(channelname);

      rtmchannel.on('ChannelMessage', ({text}, senderId) => {
        console.log('Message received: ',text);
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
        console.log(chatDiv);
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
    console.log($('#camera-list-select').val(), $('#camera-list-select').text());
    const deviceId = $('#camera-list-select').val();
    if (bclient.localVideoTrack){
      bclient.localVideoTrack.setDevice(deviceId)
    }
  })
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
      viewersStore.dispatch({payload: 'INCREASE_VIEWERS_COUNT'})
    });

    bclient.client.on('user-left', (user) => {
      console.log('user-left', user);
      viewersStore.dispatch({payload: 'DECREASE_VIEWERS_COUNT'})
    });

    const uid = await bclient.client.join(options.appId, options.channel, options.token, null);
    if (bclient.localAudioTrack === null) {
      const deviceId = $('#mic-list-select').val();
      bclient.localAudioTrack = await AgoraRTC.createMicrophoneAudioTrack({microphoneId: deviceId});
      hostStore.dispatch({type: 'AUDIO_TRACK_AVAILABLE', payload: { localAudioTrackavailable: true }});
      $('#mictoggle-btn').prop('disabled', false);
      $('#mictoggle-icon').css('color','green');
    }
    // Save this host uid on MySQL DB
    console.log(uid);
    if (bclient.localVideoTrack === null) {
      const deviceId = $('#camera-list-select').val();
      bclient.localVideoTrack = await AgoraRTC.createCameraVideoTrack({cameraId: deviceId});
      const videoElem = document.getElementById('host-video');
      bclient.localVideoTrack.play(videoElem);
      hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: true }});
      $('#camtoggle-btn').prop('disabled', false);
      $('#camtoggle-icon').css('color','green');
    }

    await bclient.client.publish([bclient.localAudioTrack, bclient.localVideoTrack]);
    $('#golive-btn').prop('disabled', true);
    $('#exit-btn').prop('disabled', false);
    await JoinChat();
  }

  async function leaveCall() {
    // Destroy the local audio and video tracks.
    bclient.localAudioTrack.close();
    bclient.localVideoTrack.close();

    bclient.localAudioTrack = null;
    bclient.localVideoTrack = null;
    hostStore.dispatch({type: 'AUDIO_TRACK_AVAILABLE', payload: { localAudioTrackavailable: false }});
    hostStore.dispatch({type: 'VIDEO_TRACK_AVAILABLE', payload: { localVideoTrackavailable: false }});

    // Leave the channel.
    await bclient.client.leave();
    rtmchannel.leave();
    RTM.rtmclient.logout();
    bclient.client = null;
    $('#exit-btn').prop('disabled', true);
    $('#golive-btn').prop('disabled', false);
  }

  const deviceIdmic = $('#mic-list-select').val();
  AgoraRTC.createMicrophoneAudioTrack({microphoneId: deviceIdmic}).then((atrack) => {
    bclient.localAudioTrack = atrack;
    hostStore.dispatch({type: 'AUDIO_TRACK_AVAILABLE', payload: { localAudioTrackavailable: true }});
    $('#mictoggle-btn').prop('disabled', false);
    $('#mictoggle-icon').css('color','green');
  })
  .catch((error) => {
    console.log(error);
  });
  const deviceIdcam = $('#camera-list-select').val();
  AgoraRTC.createCameraVideoTrack({cameraId: deviceIdcam}).then((vtrack) => {
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
    if (bclient.localVideoTrack) {
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

});
