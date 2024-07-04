from flask import Flask, request, jsonify
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.preprocessing import normalize
from sklearn.neighbors import NearestNeighbors
import numpy as np
import ast
from pymongo import MongoClient
import logging

app = Flask(__name__)

# Configure logging
logging.basicConfig(level=logging.DEBUG)

client = MongoClient('mongodb://localhost:27017/')
db = client['DavDatabase']
anime_collection = db['data_anime']
ratings_collection = db['data_ratings']

def clean_brackets(value):
    if value == "[]":
        return np.nan
    try:
        value_list = ast.literal_eval(value)
        if isinstance(value_list, list):
            return ', '.join(value_list)
    except (ValueError, SyntaxError):
        return value
    return value

def load_data():
    anime_data = pd.DataFrame(list(anime_collection.find()))
    ratings_data = pd.DataFrame(list(ratings_collection.find()))
    
    anime_data = anime_data[['_id', 'title', 'genres', 'themes', 'studios', 'producers', 'licensors']]
    
    columns_to_clean = ['genres', 'themes', 'studios', 'producers', 'licensors']
    for column in columns_to_clean:
        anime_data[column] = anime_data[column].apply(clean_brackets)
    
    anime_data = anime_data.fillna('')
    return anime_data, ratings_data

def prepare_data(anime_data):
    data = anime_data.copy()
    data['combined'] = data.apply(
        lambda row: ' '.join(row['genres'].split() + row['themes'].split() + row['studios'].split() + row['producers'].split() + row['licensors'].split()),
        axis=1
    )
    
    tfidf_vectorizer = TfidfVectorizer(
        max_df=0.8,
        min_df=2,
        stop_words='english'
    )
    
    tfidf_matrix = tfidf_vectorizer.fit_transform(data['combined'])
    tfidf_matrix_normalized = normalize(tfidf_matrix)
    
    nbrs = NearestNeighbors(n_neighbors=3, metric='cosine').fit(tfidf_matrix_normalized)
    
    return nbrs, data, tfidf_matrix_normalized

def anime_recommendations(anime_titles, nbrs, items, tfidf_matrix_normalized, k=10):
    recommendations = pd.DataFrame()
    
    for title in anime_titles:
        try:
            idx = items[items['title'] == title].index[0]
            distances, indices = nbrs.kneighbors(tfidf_matrix_normalized[idx], n_neighbors=k+1)
        except IndexError:
            app.logger.error('Anime title not found in dataset: %s', title)
            continue
        
        for i in range(1, len(indices[0])):
            recommendations = recommendations.append({
                'title': items.iloc[indices[0][i]]['title'],
                'similarity_score': 1 - distances[0][i]
            }, ignore_index=True)
    
    recommendations = recommendations.groupby('title').mean().sort_values(by='similarity_score', ascending=False).reset_index()
    
    return recommendations.head(k)

@app.route('/userRecommendations', methods=['POST'])
def user_recommendations():
    user_id = request.json.get('user_id')
    app.logger.debug('Received user_id: %s', user_id)
    if not user_id:
        return jsonify({'error': 'User ID is required'}), 400
    
    anime_data, ratings_data = load_data()
    nbrs, data, tfidf_matrix_normalized = prepare_data(anime_data)
    
    user_ratings = ratings_data[ratings_data['user_id'] == user_id]
    rated_anime_ids = user_ratings['anime_id'].tolist()
    rated_anime_titles = data[data['_id'].isin(rated_anime_ids)]['title'].tolist()

    app.logger.debug('Rated anime titles: %s', rated_anime_titles)

    if not rated_anime_titles:
        return jsonify({'error': 'No rated anime found for this user'}), 400

    recommendations = anime_recommendations(rated_anime_titles, nbrs, data, tfidf_matrix_normalized, k=10)
    
    app.logger.debug('Recommendations: %s', recommendations)

    recommendations_full = []
    for _, rec in recommendations.iterrows():
        anime_details = anime_collection.find_one({'title': rec['title']})
        if anime_details:
            anime_details['similarity_score'] = rec['similarity_score']
            recommendations_full.append({
                'title': anime_details['title'],
                'mal_id': anime_details.get('mal_id'),
                'main_picture': anime_details.get('main_picture'),
                'synopsis': anime_details.get('synopsis'),
                'similarity_score': rec['similarity_score']
            })
    
    app.logger.debug('Full Recommendations: %s', recommendations_full)
    
    return jsonify(recommendations_full)

if __name__ == '__main__':
    app.run(debug=True, port=5002)  # Change the port number to your desired port
