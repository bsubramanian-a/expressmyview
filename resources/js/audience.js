const initialState = {
  id: null,
  webcamOff: false,
  micMuted: false,
  connectionState: 'DISCONNECTED',
  remoteVideoTrackavailable: false,
  remoteScreenTrackavailable: false,
  remoteAudioTrackavailable: false,
};

const audience = (state = initialState, action) => {
  switch(action.type)
  {
    case 'SET_WEBCAM_OFF':
    {
      const { flag } = action.payload;
      return { ...state, webcamOff: flag };
    }

    case 'SET_MIC_MUTED':
    {
      const { flag } = action.payload;
      return { ...state, micMuted: flag };
    }

    case 'SET_CONN_STATE':
    {
      const { connectionState } = action.payload;
      return { ...state, connectionState };
    }

    case 'AUDIO_TRACK_AVAILABLE':
    {
      const { remoteAudioTrackavailable } = action.payload;
      return { ...state, remoteAudioTrackavailable };
    }

    case 'SCREEN_TRACK_AVAILABLE':
    {
      const { remoteScreenTrackavailable } = action.payload;
      return { ...state, remoteScreenTrackavailable };
    }

    case 'VIDEO_TRACK_AVAILABLE':
    {
      const { remoteVideoTrackavailable } = action.payload;
      return { ...state, remoteVideoTrackavailable };
    }

    default:
      return state;
  }
}

export default audience;
