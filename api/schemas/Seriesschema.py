from pydantic import BaseModel, Field, ConfigDict
from typing import Optional

class SeriesSchema(BaseModel):
    titulo: str = Field(..., description="Series Title")
    descricao: str = Field(None, description="Series Description")
    ano_lancamento: int = Field(None, description="Release Year")
    diretor: str = Field(None, description="Director")
    genero: str = Field(None, description="Genre")
    temporadas: int = Field(None, description="Number of Seasons")
    duracao: int = Field(None, description="Duration")

class SeriesCreate(SeriesSchema):
    pass

class SeriesUpdateSchema(SeriesSchema):
    titulo: str

    model_config = ConfigDict(from_attributes=True)