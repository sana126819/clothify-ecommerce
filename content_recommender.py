import pandas as pd
import mysql.connector
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.metrics.pairwise import cosine_similarity

# -------------------------------------------------
# 1. DATABASE CONNECTION
# -------------------------------------------------
conn = mysql.connector.connect(
    host="localhost",
    port=3308,          # <-- IMPORTANT
    user="root",
    password="",
    database="clothify"
)


# -------------------------------------------------
# 2. LOAD PRODUCTS
# -------------------------------------------------
query = """
SELECT
    product_id,
    description,
    main_category_id,
    sub_category_id,
    color,
    size
FROM products
"""
df = pd.read_sql(query, conn)
df.fillna("", inplace=True)

# -------------------------------------------------
# 3. BUILD WEIGHTED CONTENT
# -------------------------------------------------
def build_content(row):
    tokens = []

    # description
    if row["description"]:
        tokens.append(row["description"])

    # main category (very high)
    if row["main_category_id"]:
        tokens.extend([f"maincat_{row['main_category_id']}"] * 4)

    # sub category (high)
    if row["sub_category_id"]:
        tokens.extend([f"subcat_{row['sub_category_id']}"] * 3)

    # color (medium)
    if row["color"]:
        tokens.extend([f"color_{row['color'].lower()}"] * 2)

    # size (low)
    if row["size"]:
        tokens.append(f"size_{row['size'].lower()}")

    return " ".join(tokens)

df["content"] = df.apply(build_content, axis=1)

# -------------------------------------------------
# 4. TF-IDF
# -------------------------------------------------
vectorizer = TfidfVectorizer(stop_words="english")
tfidf_matrix = vectorizer.fit_transform(df["content"])

# -------------------------------------------------
# 5. COSINE SIMILARITY
# -------------------------------------------------
similarity_matrix = cosine_similarity(tfidf_matrix)

# -------------------------------------------------
# 6. SAVE RECOMMENDATIONS
# -------------------------------------------------
cursor = conn.cursor()
cursor.execute("DELETE FROM recommendations WHERE model = 'content'")

TOP_N = 5

for idx, product_id in enumerate(df["product_id"]):
    sims = list(enumerate(similarity_matrix[idx]))
    sims = [(i, s) for i, s in sims if i != idx]
    sims.sort(key=lambda x: x[1], reverse=True)

    for i, score in sims[:TOP_N]:
        cursor.execute(
            """
            INSERT INTO recommendations
            (product_id, recommended_product_id, score, model)
            VALUES (%s, %s, %s, 'content')
            """,
            (
                int(product_id),
                int(df.iloc[i]["product_id"]),
                round(score * 100, 2)
            )
        )

conn.commit()
cursor.close()
conn.close()

print("✅ Recommendations generated successfully")
