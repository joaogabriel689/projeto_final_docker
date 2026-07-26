from sqlalchemy.orm import declarative_base
from sqlalchemy import Column, Integer, String
Base = declarative_base()

class Series(Base):

    __tablename__ = "series"

    id = Column(Integer, primary_key=True, index=True, autoincrement=True)
    titulo = Column(String(255), nullable=False)
    descricao = Column(String(255), nullable=True)
    ano_lancamento = Column(Integer, nullable=True)
    diretor = Column(String(255), nullable=True)
    genero = Column(String(100), nullable=True)
    temporadas = Column(Integer, nullable=True)
    duracao = Column(Integer, nullable=True)
