# -*- coding: utf-8 -*-
"""davRekomendasiBasedContent.ipynb

Automatically generated by Colab.

Original file is located at
    https://colab.research.google.com/drive/14Wp8DzFBZNb04vNS4onJTdGvwjDdm9tc

# Sistem rekomendasi Based Content

## Import Library
"""
import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity
from pymongo import MongoClient
import numpy as np

"""## Load data"""

# Ubah 'your_connection_string' dengan string koneksi MongoDB Anda
client = MongoClient('mongodb+srv://ricardodirkanderson:rikupang@cluster0.x3m6qzb.mongodb.net/?retryWrites=true&w=majority&appName=Cluster0')

# Pilih database dan koleksi
db = client['DavDatabase']
collection = db['data_anime']

# Ambil data dari koleksi MongoDB
data_from_db = list(collection.find())

# Konversi data ke DataFrame pandas
df = pd.DataFrame(data_from_db)

# Tampilkan data
print(df.head())

# Tampilkan daftar kolom
df.info()

anime_data = df[['title','genres','themes','studios','producers','licensors']]

anime_data

print("Jumlah data nama anime adalah: ", len(anime_data.title.unique()))
print("Jumlah data nama genre adalah: ", len(anime_data.genres.unique()))
print("Jumlah data nama studio adalah: ", len(anime_data.studios.unique()))
print("Jumlah data nama tema adalah: ", len(anime_data.themes.unique()))
print("Jumlah data nama licensors adalah: ", len(anime_data.licensors.unique()))
print("Jumlah data nama producers adalah: ", len(anime_data.producers.unique()))

"""## Data preprocessing"""

import ast

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

# Terapkan fungsi ke kolom yang sesuai
columns_to_clean = ['genres', 'themes', 'studios', 'producers', 'licensors']
for column in columns_to_clean:
    anime_data[column] = anime_data[column].apply(clean_brackets)

# Tampilkan DataFrame yang sudah dibersihkan
print(anime_data)

anime_data.isnull().sum()

anime_data

"""mengubah data menjad list"""

# konversi data series menjadi list
# title            0
# genres        4682
# themes       10070
# studios       9352
# producers    12369
# licensors    19503

genres = anime_data['genres'].tolist()
title = anime_data['title'].tolist()
producers = anime_data['producers'].tolist()
studios = anime_data['studios'].tolist()
licensors = anime_data['licensors'].tolist()
themes = anime_data['themes'].tolist()

print(len(genres))
print(len(title))
print(len(producers))
print(len(studios))
print(len(licensors))
print(len(themes))

title

anime_data = list(map(lambda st: str.replace(st, "\xa0", ""), anime_data))
anime_data

# Membuat DataFrame baru
new_data = pd.DataFrame({
    'title': title,
    'genres': genres,
    'themes': themes,
    'studios': studios,
    'producers': producers,
    'licensors': licensors
})

new_data.head(20)

data = new_data
data.sample(10)

"""ubah data nan menjad strng kosong agar diabaikan saat melakukan feuture extraction"""

# Pastikan data tidak ada nilai null
data = data.fillna('')

"""gabungkan semua column"""

# Gabungkan kolom menjadi satu kolom teks besar
data['combined'] = data.apply(
    lambda row: ' '.join(row['genres'].split() + row['themes'].split() + row['studios'].split() + row['producers'].split() + row['licensors'].split()),
    axis=1
)

"""Melakukan feuture extraction"""

# Inisialisasi TfidfVectorizer dengan parameter tambahan
tfidf_vectorizer = TfidfVectorizer(
    max_df=0.8,  # Memotong kata-kata yang muncul di lebih dari 80% dokumen
    min_df=2,    # Mengabaikan kata-kata yang muncul di kurang dari 2 dokumen
    max_features=None,  # Gunakan semua fitur yang memenuhi threshold
    stop_words='english'  # Menggunakan stopwords bahasa Inggris
)

# Melakukan fit_transform pada data yang digabungkan
tfidf_matrix = tfidf_vectorizer.fit_transform(data['combined'])

from sklearn.preprocessing import normalize
# Normalisasi matriks tf-idf
tfidf_matrix_normalized = normalize(tfidf_matrix)



"""## Membuat sistem rekomendasi dengan Cosine Similiarity

Menghitung cosine similiarity pada matrix tf idf
"""

# Menghitung cosine similarity pada matrix tf-idf yang sudah dinormalisasi
genre_sim = cosine_similarity(tfidf_matrix_normalized)

genre_sim

# Mengubah hasil cosine similarity menjadi DataFrame
similarity_df = pd.DataFrame(genre_sim, index=data.title, columns=data.title)

similarity_df

"""membuat fungsi untuk rekomendasi anime"""

# Fungsi untuk rekomendasi
def anime_recommendations(nama_anime_list, similarity_data, items, k=20):
    similar_scores = pd.DataFrame()
    for nama_anime in nama_anime_list:
        similar_scores = pd.concat([similar_scores, similarity_data.loc[:, nama_anime]], axis=1)

    similar_scores['mean_similarity'] = similar_scores.mean(axis=1)
    similar_scores = similar_scores.drop(nama_anime_list, errors='ignore')

    similar_scores = similar_scores.nlargest(k, 'mean_similarity')

    recommendations = pd.DataFrame({'title': similar_scores.index, 'similarity_score': similar_scores['mean_similarity'].values})
    recommendations = recommendations.merge(items, on='title')
    recommendations = recommendations.sort_values(by='similarity_score', ascending=False)

    return recommendations.head(k)

data[data.title.eq('Meitantei Conan')]

# nama_anime_list = ['The One Piece','Dragon Ball','Naruto','Meitantei Conan']  # Ganti dengan daftar anime yang diinginkan
nama_anime_list = ['Dragon Ball Z']  # Ganti dengan daftar anime yang diinginkan

recommendations = anime_recommendations(nama_anime_list, similarity_df, data, k=20)

recommendations