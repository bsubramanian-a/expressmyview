const initialState = {
  viewersCount: 0,
  hostConnected: false,
  totalviews: 0,
  noOfHosts: 0,
  hostsList: {},
  audioTracks: {},
  videoTracks: {},
  numAudioTracks: 0,
  numVideoTracks: 0,
};

const connectedHosts =(state = initialState, action) => {
  switch (action.type) {
    case 'INCREASE_VIEWERS_COUNT':
    {
      const { viewersCount } = state;
      return { ...state, viewersCount: viewersCount + 1}
    }

    case 'DECREASE_VIEWERS_COUNT':
    {
      const { viewersCount } = state;
      return { ...state, viewersCount: viewersCount - 1}
    }

    case 'INCREASE_ATRACK_COUNT':
    {
      const { numAudioTracks } = state;
      return { ...state, numAudioTracks: numAudioTracks + 1}
    }

    case 'DECREASE_ATRACK_COUNT':
    {
      const { numAudioTracks } = state;
      return { ...state, numAudioTracks: numAudioTracks - 1}
    }

    case 'INCREASE_VTRACK_COUNT':
    {
      const { numVideoTracks } = state;
      return { ...state, numVideoTracks: numVideoTracks + 1}
    }

    case 'DECREASE_VTRACK_COUNT':
    {
      const { numVideoTracks } = state;
      return { ...state, numVideoTracks: numVideoTracks - 1}
    }

    case 'HOST_CONNECTED':
    {
      const { hostConnected } = action.payload;
      return { ...state, hostConnected };
    }

    case 'ADD_HOST_TO_LIST':
    {
      const { host } = action.payload;
      const { hostsList, noOfHosts } = state;
      hostsList[host.uid] = host;
      return { ...state, hostsList , noOfHosts: noOfHosts + 1 };
    }

    case 'REMOVE_HOST_FROM_LIST':
    {
      const { hostid } = action.payload;
      const { hostsList, noOfHosts } = state;
      delete hostsList[hostid]
      return { ...state, hostsList, noOfHosts: noOfHosts - 1 };
    }

    default:
      return state;
  }
};

export default connectedHosts;
