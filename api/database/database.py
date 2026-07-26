from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker
from os import getenv

DATABASE_URL = getenv("DATABASE_URL")

engine = create_engine(DATABASE_URL, echo=True)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
