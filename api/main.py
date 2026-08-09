from fastapi import FastAPI, Depends, HTTPException
from sqlalchemy.orm import Session
from typing import List

from database.database import SessionLocal, engine
from models.Seriesmodel import Series, Base
from schemas.Seriesschema import SeriesSchema, SeriesCreate, SeriesUpdateSchema
import docker

Base.metadata.create_all(bind=engine)

app = FastAPI()


def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()


@app.get("/", response_model=List[SeriesSchema])
async def read_root(skip: int = 0, limit: int = 100, genero: str = None, ano_lancamento: int = None, db: Session = Depends(get_db)):
    query = db.query(Series)
    if genero:
        query = query.filter(Series.genero == genero)
    if ano_lancamento:
        query = query.filter(Series.ano_lancamento == ano_lancamento)
    series = query.offset(skip).limit(limit).all()
    print(series)
    return series


@app.post("/series/", response_model=SeriesSchema)
async def create_series(series: SeriesCreate, db: Session = Depends(get_db)):
    nova_series = Series(**series.model_dump())
    db.add(nova_series)
    db.commit()
    db.refresh(nova_series)
    return nova_series


@app.get("/series/{series_id}", response_model=SeriesSchema)
async def read_series(series_id: str, db: Session = Depends(get_db)):
    series = db.query(Series).filter(Series.titulo == series_id).first()
    if series is None:
        raise HTTPException(status_code=404, detail="Series not found")
    return series


@app.put("/series/{series_id}", response_model=SeriesSchema)
async def update_series(series_id: str, updated_series: SeriesUpdateSchema, db: Session = Depends(get_db)):
    series = db.query(Series).filter(Series.titulo == series_id).first()
    if series is None:
        raise HTTPException(status_code=404, detail="Series not found")

    for key, value in updated_series.model_dump(exclude_unset=True).items():
        setattr(series, key, value)

    db.commit()
    db.refresh(series)
    return series


@app.delete("/series/{series_id}")
async def delete_series(series_id: str, db: Session = Depends(get_db)):
    series = db.query(Series).filter(Series.titulo   == series_id).first()
    if series is None:
        raise HTTPException(status_code=404, detail="Series not found")

    db.delete(series)
    db.commit()
    return {"message": "Series deleted successfully"}


@app.get("/health")
async def health_check():
    return {"status": "healthy"}


@app.get("/containers")
async def list_containers():
