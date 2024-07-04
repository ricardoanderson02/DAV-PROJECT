from flask import Flask, request, jsonify
import numpy as np
import pandas as pd
import tensorflow as tf

from pymongo import MongoClient

app = Flask(__name__)

client = MongoClient('mongodb://localhost:27017/')
db = client['DavDatabase']
anime_collection = db['data_anime']
ratings_collection = db['data_ratings']

# Define the RecommenderNet model
class RecommenderNet(tf.keras.Model):
    def __init__(self, num_users, num_anime, embedding_size, **kwargs):
        super(RecommenderNet, self).__init__(**kwargs)
        self.user_embedding = tf.keras.layers.Embedding(
            num_users,
            embedding_size,
            embeddings_initializer='he_normal',
            embeddings_regularizer=tf.keras.regularizers.l2(1e-6)
        )
        self.user_bias = tf.keras.layers.Embedding(num_users, 1)
        self.anime_embedding = tf.keras.layers.Embedding(
            num_anime,
            embedding_size,
            embeddings_initializer='he_normal',
            embeddings_regularizer=tf.keras.regularizers.l2(1e-6)
        )
        self.anime_bias = tf.keras.layers.Embedding(num_anime, 1)

    def call(self, inputs):
        user_vector = self.user_embedding(inputs[:, 0])
        user_bias = self.user_bias(inputs[:, 0])
        anime_vector = self.anime_embedding(inputs[:, 1])
        anime_bias = self.anime_bias(inputs[:, 1])
        dot_user_anime = tf.tensordot(user_vector, anime_vector, 2)
        x = dot_user_anime + user_bias + anime_bias
        return tf.nn.sigmoid(x)

# Load model with a dummy num_users and num_anime, these will be updated dynamically
model = RecommenderNet(1, 1, embedding_size=12)
model.compile(
    loss=tf.keras.losses.BinaryCrossentropy(),
    optimizer=tf.keras.optimizers.Adam(learning_rate=0.001),
    metrics=[tf.keras.metrics.RootMeanSquaredError()]
)
model.load_weights('best_model_cbl')  # Load the trained weights

@app.route('/recommend', methods=['GET'])
def recommend():
    # Load data from MongoDB
    anime_df = pd.DataFrame(list(anime_collection.find()))
    ratings = pd.DataFrame(list(ratings_collection.find()))

    user_ids = ratings['user_id'].unique().tolist()
    user_to_user_encoded = {x: i for i, x in enumerate(user_ids)}
    user_encoded_to_user = {i: x for i, x in enumerate(user_ids)}

    anime_ids = ratings['anime_id'].unique().tolist()
    anime_id_to_anime_id_encoded = {x: i for i, x in enumerate(anime_ids)}
    anime_id_encoded_to_anime_id = {i: x for i, x in enumerate(anime_ids)}

    num_users = len(user_to_user_encoded)
    num_anime = len(anime_id_encoded_to_anime_id)

    # Update model with correct number of users and anime
    model.user_embedding = tf.keras.layers.Embedding(
        num_users,
        model.user_embedding.output_dim,
        embeddings_initializer='he_normal',
        embeddings_regularizer=tf.keras.regularizers.l2(1e-6)
    )
    model.user_bias = tf.keras.layers.Embedding(num_users, 1)
    model.anime_embedding = tf.keras.layers.Embedding(
        num_anime,
        model.anime_embedding.output_dim,
        embeddings_initializer='he_normal',
        embeddings_regularizer=tf.keras.regularizers.l2(1e-6)
    )
    model.anime_bias = tf.keras.layers.Embedding(num_anime, 1)

    user_id = request.args.get('user_id')
    if user_id not in user_to_user_encoded:
        return jsonify({"error": "User ID not found"}), 404

    user_encoder = user_to_user_encoded[user_id]
    anime_read_by_user = ratings[ratings['user_id'] == user_id]
    anime_not_read = anime_df[~anime_df['_id'].isin(anime_read_by_user['anime_id'].values)]['_id']
    anime_not_read = list(set(anime_not_read).intersection(set(anime_id_to_anime_id_encoded.keys())))
    anime_not_read = [[anime_id_to_anime_id_encoded.get(x)] for x in anime_not_read]
    user_anime_array = np.hstack(([[user_encoder]] * len(anime_not_read), anime_not_read))
    
    ratings_pred = model.predict(user_anime_array).flatten()
    top_ratings_indices = ratings_pred.argsort()[-10:][::-1]
    recommended_anime_ids = [anime_id_encoded_to_anime_id.get(anime_not_read[x][0]) for x in top_ratings_indices]
    recommended_animes = anime_df[anime_df['_id'].isin(recommended_anime_ids)]
    
    recommendations_full = []
    for _, anime_details in recommended_animes.iterrows():
        recommendations_full.append({
            'title': anime_details['title'],
            'mal_id': anime_details['mal_id'],
            'main_picture': anime_details['main_picture'],
            'synopsis': anime_details['synopsis']
        })
    
    return jsonify(recommendations_full)

if __name__ == '__main__':
    app.run(debug=True, port=5001)  # Change the port number to your desired port
