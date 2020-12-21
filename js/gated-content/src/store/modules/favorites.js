import client from '@/client';

export default {
  state: {
    data: {
      node: {
        vy_blog_post: [],
        gc_video: [],
      },
      eventinstance: {
        live_stream: [],
        virtual_meeting: [],
      },
      taxonomy_term: {
        gc_category: [],
      },
    },
  },
  actions: {
    async loadFavorites(context) {
      client
        .get('/api/favorites')
        .then((response) => {
          context.commit('add', response.data);
        })
        .catch((error) => {
          console.error(error);
          throw error;
        });
    },
    async addItemToFavorites(context, payload) {
      return client({
        url: '/api/favorites/add',
        method: 'post',
        params: {
          _format: 'json',
        },
        data: {
          entity_type: payload.type,
          entity_bundle: payload.bundle,
          entity_id: payload.id,
        },
      })
        .then((response) => {
          context.commit('add', response.data);
        })
        .catch((error) => {
          console.error(error);
          throw error;
        });
    },
    async deleteItemFromFavorites(context, payload) {
      return client({
        url: '/api/favorites/delete',
        method: 'delete',
        params: {
          _format: 'json',
        },
        data: {
          entity_type: payload.type,
          entity_bundle: payload.bundle,
          entity_id: payload.id,
        },
      })
        .then((response) => {
          context.commit('add', response.data);
        })
        .catch((error) => {
          console.error(error);
          throw error;
        });
    },
  },
  mutations: {
    add(state, payload) {
      state.data = payload;
    },
  },
  getters: {
    getFavoritesList: (state) => state.data,
    isFavorite: (state) => (payload) => state.data[payload.type][payload.bundle]
      .some((item) => parseInt(item.entity_id, 10) === parseInt(payload.id, 10)),
  },
};
