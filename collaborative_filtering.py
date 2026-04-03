import pandas as pd
import mysql.connector
from sklearn.metrics.pairwise import cosine_similarity

# -------------------------
# 1️⃣ Connect to database
# -------------------------
conn = mysql.connector.connect(
    host="localhost",
    port=3308,
    user="root",
    password="",
    database="clothify"
)
cursor = conn.cursor()

# -------------------------
# 2️⃣ Load user activity
# -------------------------
query_activity = "SELECT user_id, product_id, action FROM user_activity"
df_activity = pd.read_sql(query_activity, conn)

# Exit if no activity
if df_activity.empty:
    print("No user activity data found.")
    cursor.close()
    conn.close()
    exit()

# Map actions to scores
action_score = {
    "view": 0.05,
    "cart": 0.2,
    "wishlist": 0.5,
    "wishlist_removed": -0.5,
    "purchase": 1.0
}
df_activity["score"] = df_activity["action"].map(action_score).fillna(0)

# -------------------------
# 3️⃣ Load user ratings (reviews)
# -------------------------
query_ratings = "SELECT user_id, product_id, rating FROM reviews"  # Fixed table name
df_ratings = pd.read_sql(query_ratings, conn)

if not df_ratings.empty:
    # Convert rating (1–5) to 0–1 scale
    df_ratings["score"] = df_ratings["rating"] / 5.0

# -------------------------
# 4️⃣ Load purchase data from orders
# -------------------------
query_orders = """
    SELECT user_id, product_id 
    FROM orders 
    WHERE order_status IN ('completed', 'pending')
"""
df_orders = pd.read_sql(query_orders, conn)

if not df_orders.empty:
    # Purchases get highest weight
    df_orders["score"] = 1.0
    
# -------------------------
# 5️⃣ Combine ALL data (REMOVED DUPLICATE!)
# -------------------------
df_combined = pd.concat([
    df_activity[["user_id","product_id","score"]],
    df_ratings[["user_id","product_id","score"]] if not df_ratings.empty else pd.DataFrame(),
    df_orders[["user_id","product_id","score"]] if not df_orders.empty else pd.DataFrame()
], ignore_index=True)

# Check if we have data
if df_combined.empty:
    print("No combined data found. Exiting.")
    cursor.close()
    conn.close()
    exit()

# -------------------------
# 6️⃣ Create product-user matrix
# -------------------------
user_item = df_combined.pivot_table(
    index='product_id',
    columns='user_id',
    values='score',
    aggfunc='sum',
    fill_value=0
)

# Filter products with very few users
min_users = 2
user_counts = (user_item > 0).sum(axis=1)
user_item = user_item[user_counts >= min_users]

if user_item.shape[0] == 0:
    print("No products have enough interactions. Exiting.")
    cursor.close()
    conn.close()
    exit()

# -------------------------
# 7️⃣ Compute item-item similarity
# -------------------------
similarity_matrix = cosine_similarity(user_item)
similarity_df = pd.DataFrame(
    similarity_matrix,
    index=user_item.index,
    columns=user_item.index
)

# -------------------------
# 8️⃣ Save top-N recommendations
# -------------------------
TOP_N = 5
cursor.execute("DELETE FROM recommendations WHERE model = 'collaborative'")

for product_id in similarity_df.index:
    sims = similarity_df.loc[product_id].sort_values(ascending=False)
    sims = sims.drop(product_id, errors='ignore')
    for rec_product_id, score in sims.head(TOP_N).items():
        percentage = round(score * 100, 2)
        cursor.execute(
            """
            INSERT INTO recommendations
            (product_id, recommended_product_id, score, model)
            VALUES (%s, %s, %s, 'collaborative')
            """,
            (int(product_id), int(rec_product_id), percentage)
        )
        print(f"Product {product_id} -> {rec_product_id}: {percentage}%")

# -------------------------
# 9️⃣ Close DB connection
# -------------------------
conn.commit()
cursor.close()
conn.close()
print("✅ Collaborative filtering recommendations generated with reviews AND orders!")