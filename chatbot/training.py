import openai
import google.generativeai as genai
from openai import OpenAI
import os
import json

# ===== CONFIGURATION =====
GOOGLE_API_KEY = "AIzaSyC_Nw92MdbelqAdnSccrVk5GOQkN2c4ddo"   # Replace with your key or use environment variables
TRAINING_FILE = "fine_tune_data.jsonl"  # Your prepared JSONL file
MODEL_NAME = "gpt-3.5-turbo"  # Base model for fine-tuning
SUFFIX = "medical-qa"  # Custom identifier for your model

# ===== STEP 1: Initialize Client =====
client=genai.configure(api_key=GOOGLE_API_KEY)

# ===== STEP 2: Upload Training File =====
def upload_training_file():
    try:
        file = client.files.create(
            file=open(TRAINING_FILE, "rb"),
            purpose="fine-tune"
        )
        print(f"✅ File uploaded (ID: {file.id})")
        return file.id
    except Exception as e:
        print(f"❌ Upload failed: {e}")
        return None

# ===== STEP 3: Start Fine-Tuning Job =====
def start_fine_tuning(file_id):
    try:
        response = client.fine_tuning.jobs.create(
            training_file=file_id,
            model=MODEL_NAME,
            suffix=SUFFIX
        )
        print(f"✅ Fine-tuning started (Job ID: {response.id})")
        print(f"Track progress: https://platform.openai.com/finetune")
        return response.id
    except Exception as e:
        print(f"❌ Fine-tuning failed: {e}")
        return None

# ===== STEP 4: Monitor Job Status =====
def monitor_job(job_id):
    import time
    while True:
        job = client.fine_tuning.jobs.retrieve(job_id)
        print(f"Status: {job.status} | Trained Tokens: {getattr(job, 'trained_tokens', 'N/A')}")
        
        if job.status in ["succeeded", "failed", "cancelled"]:
            if job.status == "succeeded":
                print(f"🎉 Model fine-tuned successfully!")
                print(f"Model ID: {job.fine_tuned_model}")
            return job.fine_tuned_model if job.status == "succeeded" else None
        
        time.sleep(60)  # Check every minute

# ===== STEP 5: Test Fine-Tuned Model =====
def test_model(model_id, test_question="What are diabetes symptoms?"):
    try:
        response = client.chat.completions.create(
            model=model_id,
            messages=[{"role": "user", "content": test_question}]
        )
        print(f"\n🧪 Test Question: {test_question}")
        print(f"🤖 Model Response: {response.choices[0].message.content}")
    except Exception as e:
        print(f"❌ Test failed: {e}")

# ===== MAIN EXECUTION =====
if __name__ == "__main__":
    print("=== Starting Fine-Tuning Process ===")
    
    # Step 1: Upload file
    file_id = upload_training_file()
    if not file_id:
        exit(1)
    
    # Step 2: Start fine-tuning
    job_id = start_fine_tuning(file_id)
    if not job_id:
        exit(1)
    
    # Step 3: Monitor and wait for completion
    print("\n⏳ Monitoring fine-tuning progress...")
    model_id = monitor_job(job_id)
    
    # Step 4: Test the model
    if model_id:
        test_model(model_id)